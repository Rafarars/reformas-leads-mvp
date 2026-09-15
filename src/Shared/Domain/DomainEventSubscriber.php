<?php

namespace Reformas\Shared\Domain;

interface DomainEventSubscriber
{
    /**
     * @return list<class-string<DomainEvent>>
     */
    public static function subscribedTo(): array;

    public function __invoke(DomainEvent $event): void;
}
