<?php

namespace Reformas\Delivery\Outbox\Domain;

// El estado se comparte entre todos los procesos que entregan: si uno ve el servicio caído, los demás
// dejan de intentarlo también.
interface CircuitBreakerStore
{
    public function state(): CircuitBreakerState;

    public function save(CircuitBreakerState $state): void;
}
