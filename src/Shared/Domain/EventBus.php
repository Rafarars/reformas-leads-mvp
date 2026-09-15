<?php

namespace Reformas\Shared\Domain;

interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
