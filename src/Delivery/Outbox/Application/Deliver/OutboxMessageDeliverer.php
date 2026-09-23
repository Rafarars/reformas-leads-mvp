<?php

namespace Reformas\Delivery\Outbox\Application\Deliver;

use Reformas\Delivery\Outbox\Domain\CircuitBreaker;
use Reformas\Delivery\Outbox\Domain\MessageDeliverer;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use Reformas\Delivery\Outbox\Domain\RetryPolicy;
use Reformas\Shared\Domain\Clock;

final readonly class OutboxMessageDeliverer
{
    public function __construct(
        private OutboxRepository $outbox,
        private MessageDeliverer $deliverer,
        private CircuitBreaker $circuitBreaker,
        private RetryPolicy $retryPolicy,
        private Clock $clock,
    ) {}

    public function __invoke(OutboxMessageDelivererRequest $request): DeliveryOutcome
    {
        $message = $this->outbox->search(new OutboxMessageId($request->messageId));

        // Ya entregado o apartado: un trabajo duplicado no lo vuelve a enviar.
        if ($message === null || ! $message->isPending()) {
            return DeliveryOutcome::skipped();
        }

        $now = $this->clock->now();
        $nextAttemptAt = $message->nextAttemptAt();

        // Un trabajo duplicado (el del barrido) que llega antes de tiempo no adelanta el reintento.
        if ($nextAttemptAt !== null && $nextAttemptAt > $now) {
            return DeliveryOutcome::retryScheduled($nextAttemptAt);
        }

        $destination = $message->destination();
        $openUntil = $this->circuitBreaker->openUntil($destination, $now);

        // Con el circuito abierto no se intenta ni se gasta un intento: se espera a que se cierre.
        if ($openUntil !== null) {
            return DeliveryOutcome::circuitOpen($openUntil);
        }

        $result = $this->deliverer->deliver($message);

        if ($result->succeeded) {
            $message->markDelivered($now);
            $this->circuitBreaker->recordSuccess($destination);
            $this->outbox->save($message);

            return DeliveryOutcome::delivered();
        }

        // Un fallo permanente (bot bloqueado o chat inexistente) va directo a la DLQ y no cuenta
        // para el circuito de todo el equipo: aislar el fallo en el usuario roto evita bloquear a los demás.
        if ($result->isPermanent) {
            $reason = 'Fallo permanente: '.(string) $result->failureReason;
            $message->markDead($reason);
            $this->outbox->save($message);

            return DeliveryOutcome::gaveUp();
        }

        $this->circuitBreaker->recordFailure($destination, $now);
        $reason = (string) $result->failureReason;

        // Apartado, nunca descartado: queda con su último error para reprocesarlo a mano.
        if ($this->retryPolicy->shouldGiveUp($message->retryWindowStartedAt(), $now)) {
            $message->markDead($reason);
            $this->outbox->save($message);

            return DeliveryOutcome::gaveUp();
        }

        $retryAt = $this->retryPolicy->nextAttemptAt($message->attempts() + 1, $now);
        $message->recordFailedAttempt($reason, $retryAt);
        $this->outbox->save($message);

        return DeliveryOutcome::retryScheduled($retryAt);
    }
}
