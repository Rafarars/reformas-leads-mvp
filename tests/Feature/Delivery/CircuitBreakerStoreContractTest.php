<?php

use Reformas\Delivery\Outbox\Domain\CircuitBreakerState;
use Reformas\Delivery\Outbox\Domain\CircuitBreakerStore;
use Reformas\Delivery\Outbox\Infrastructure\Cache\CacheCircuitBreakerStore;
use Reformas\Delivery\Outbox\Infrastructure\InMemory\InMemoryCircuitBreakerStore;

dataset('circuit breaker stores', [
    'in memory' => fn () => new InMemoryCircuitBreakerStore,
    'cache' => fn () => new CacheCircuitBreakerStore,
]);

it('starts closed', function (CircuitBreakerStore $store) {
    expect($store->state('crm'))->toEqual(CircuitBreakerState::closed());
})->with('circuit breaker stores');

it('gives back the state it was given', function (CircuitBreakerStore $store) {
    $state = new CircuitBreakerState(5, new DateTimeImmutable('2026-09-15T10:00:30+00:00'));

    $store->save('crm', $state);

    expect($store->state('crm')->consecutiveFailures)->toBe(5)
        ->and($store->state('crm')->openUntil?->format(DATE_ATOM))->toBe('2026-09-15T10:00:30+00:00');
})->with('circuit breaker stores');
