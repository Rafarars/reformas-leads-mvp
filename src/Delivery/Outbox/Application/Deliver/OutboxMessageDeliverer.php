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
        $openUntil = $this->circuitBreaker->openUntil($now);

        // Con el circuito abierto no se intenta ni se gasta un intento: se espera a que se cierre.
        if ($openUntil !== null) {
            return DeliveryOutcome::circuitOpen($openUntil);
        }

        $result = $this->deliverer->deliver($message);

        if ($result->succeeded) {
            $message->markDelivered($now);
            $this->circuitBreaker->recordSuccess();
            $this->outbox->save($message);

            return DeliveryOutcome::delivered();
        }

        $this->circuitBreaker->recordFailure($now);
        $reason = (string) $result->failureReason;

        // Apartado, nunca descartado: queda con su último error para reprocesarlo a mano.
        if ($this->retryPolicy->shouldGiveUp($message->createdAt(), $now)) {
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
