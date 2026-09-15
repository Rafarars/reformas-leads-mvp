<?php

namespace Reformas\Delivery\Outbox\Application\Replay;

use Reformas\Delivery\Outbox\Domain\DeliveryQueue;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;
use Reformas\Delivery\Outbox\Domain\OutboxMessageNotDeadError;
use Reformas\Delivery\Outbox\Domain\OutboxMessageNotFoundError;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use Reformas\Shared\Domain\Clock;

// Reprocesar a mano un mensaje apartado, una vez arreglada la causa.
final readonly class OutboxMessageReplayer
{
    public function __construct(
        private OutboxRepository $outbox,
        private DeliveryQueue $queue,
        private Clock $clock,
    ) {}

    public function __invoke(OutboxMessageReplayerRequest $request): void
    {
        $id = new OutboxMessageId($request->messageId);
        $message = $this->outbox->search($id) ?? throw new OutboxMessageNotFoundError($request->messageId);

        // Solo lo apartado: reintentar uno pendiente o ya entregado duplicaría el envío.
        if (! $message->isDead()) {
            throw new OutboxMessageNotDeadError($request->messageId);
        }

        $message->replay($this->clock->now());
        $this->outbox->save($message);
        $this->queue->enqueue($id);
    }
}
