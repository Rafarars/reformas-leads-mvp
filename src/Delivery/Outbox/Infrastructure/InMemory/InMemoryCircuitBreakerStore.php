<?php

namespace Reformas\Delivery\Outbox\Infrastructure\InMemory;

use Reformas\Delivery\Outbox\Domain\CircuitBreakerState;
use Reformas\Delivery\Outbox\Domain\CircuitBreakerStore;

final class InMemoryCircuitBreakerStore implements CircuitBreakerStore
{
    private CircuitBreakerState $state;

    public function __construct()
    {
        $this->state = CircuitBreakerState::closed();
    }

    public function state(): CircuitBreakerState
    {
        return $this->state;
    }

    public function save(CircuitBreakerState $state): void
    {
        $this->state = $state;
    }
}
