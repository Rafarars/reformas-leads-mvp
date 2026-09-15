<?php

namespace Reformas\Delivery\Outbox\Infrastructure\Queue;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Reformas\Delivery\Outbox\Application\Deliver\OutboxMessageDeliverer;
use Reformas\Delivery\Outbox\Application\Deliver\OutboxMessageDelivererRequest;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use Reformas\Shared\Domain\Clock;
use Throwable;

final class DeliverOutboxMessageJob implements ShouldQueue
{
    use Queueable;

    // Sin límite de intentos de la cola: cuándo rendirse lo decide RetryPolicy por la antigüedad del mensaje.
    public int $tries = 0;

    // Un error inesperado (un fallo de programación, no una caída del destino) no se reintenta para siempre.
    public int $maxExceptions = 3;

    public int $timeout = 30;

    public function __construct(public readonly string $messageId) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        // Dos trabajos del mismo mensaje (el despachado y el del barrido) nunca a la vez.
        return [(new WithoutOverlapping($this->messageId))->releaseAfter(10)->expireAfter(60)];
    }

    public function handle(OutboxMessageDeliverer $deliver, Clock $clock): void
    {
        $outcome = $deliver(new OutboxMessageDelivererRequest($this->messageId));

        if ($outcome->retryAt !== null) {
            $this->release(max(1, $outcome->retryAt->getTimestamp() - $clock->now()->getTimestamp()));
        }
    }

    public function failed(Throwable $exception): void
    {
        $outbox = app(OutboxRepository::class);
        $message = $outbox->search(new OutboxMessageId($this->messageId));

        // Apartado con su error, no perdido: queda para reprocesarlo desde el panel.
        if ($message !== null && $message->isPending()) {
            $message->markDead($exception::class.': '.$exception->getMessage());
            $outbox->save($message);
        }
    }
}
