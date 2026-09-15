<?php

namespace Reformas\Shared\Infrastructure;

use Reformas\Shared\Domain\DomainEvent;
use Reformas\Shared\Domain\DomainEventSubscriber;
use Reformas\Shared\Domain\EventBus;

// Primero publica hacia fuera (outbox) y luego avisa a los suscriptores internos, en la misma
// transacción. Los suscriptores solo encolan trabajo: nada lento ocurre aquí.
final readonly class SubscribersEventBus implements EventBus
{
    /**
     * @param  list<DomainEventSubscriber>  $subscribers
     */
    public function __construct(
        private EventBus $inner,
        private array $subscribers,
    ) {}

    public function publish(DomainEvent ...$events): void
    {
        $this->inner->publish(...$events);

        foreach ($events as $event) {
            foreach ($this->subscribers as $subscriber) {
                if (in_array($event::class, $subscriber::subscribedTo(), true)) {
                    $subscriber($event);
                }
            }
        }
    }
}
