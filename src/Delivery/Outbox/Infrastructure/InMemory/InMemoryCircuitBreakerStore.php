<?php

namespace Reformas\Delivery\Outbox\Infrastructure\InMemory;

use Reformas\Delivery\Outbox\Domain\CircuitBreakerState;
use Reformas\Delivery\Outbox\Domain\CircuitBreakerStore;

final class InMemoryCircuitBreakerStore implements CircuitBreakerStore
{
    /** @var array<string, CircuitBreakerState> */
    private array $states = [];

    public function state(string $destination): CircuitBreakerState
    {
        return $this->states[$destination] ?? CircuitBreakerState::closed();
    }

    public function save(string $destination, CircuitBreakerState $state): void
    {
        $this->states[$destination] = $state;
    }
}
