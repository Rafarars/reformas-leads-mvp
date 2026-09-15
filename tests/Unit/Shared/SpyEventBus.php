<?php

namespace Tests\Unit\Shared;

use Reformas\Shared\Domain\DomainEvent;
use Reformas\Shared\Domain\EventBus;

final class SpyEventBus implements EventBus
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->published[] = $event;
        }
    }
}
