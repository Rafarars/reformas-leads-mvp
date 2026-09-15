<?php

namespace Reformas\Delivery\Outbox\Infrastructure;

use Reformas\Delivery\Outbox\Domain\DeliveryQueue;
use Reformas\Delivery\Outbox\Domain\OutboxMessage;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use Reformas\Shared\Domain\Clock;
use Reformas\Shared\Domain\DomainEvent;
use Reformas\Shared\Domain\EventBus;
use Reformas\Shared\Domain\UuidGenerator;

// Publicar no es enviar: el evento se escribe en la base, en la transacción de quien lo publica.
// La entrega a sistemas externos ocurre después y se puede reintentar sin perder nada.
final readonly class OutboxEventBus implements EventBus
{
    public function __construct(
        private OutboxRepository $outbox,
        private UuidGenerator $uuidGenerator,
        private Clock $clock,
        private DeliveryQueue $queue,
    ) {}

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $id = new OutboxMessageId($this->uuidGenerator->generate());

            $this->outbox->save(OutboxMessage::create(
                $id,
                $event::eventName(),
                $event->aggregateId,
                $event->body(),
                $this->clock->now(),
            ));

            $this->queue->enqueue($id);
        }
    }
}
