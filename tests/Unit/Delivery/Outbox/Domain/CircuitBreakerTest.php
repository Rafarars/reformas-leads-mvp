<?php

use Reformas\Delivery\Outbox\Domain\CircuitBreaker;
use Reformas\Delivery\Outbox\Infrastructure\InMemory\InMemoryCircuitBreakerStore;

beforeEach(function () {
    $this->breaker = new CircuitBreaker(new InMemoryCircuitBreakerStore, failureThreshold: 5, cooldownSeconds: 30);
    $this->now = new DateTimeImmutable('2026-09-15T10:00:00+00:00');
});

it('stays closed below the failure threshold', function () {
    foreach (range(1, 4) as $ignored) {
        $this->breaker->recordFailure('crm', $this->now);
    }

    expect($this->breaker->openUntil('crm', $this->now))->toBeNull();
});

it('opens for the cooldown after consecutive failures', function () {
    foreach (range(1, 5) as $ignored) {
        $this->breaker->recordFailure('crm', $this->now);
    }

    expect($this->breaker->openUntil('crm', $this->now)?->format(DATE_ATOM))->toBe('2026-09-15T10:00:30+00:00')
        ->and($this->breaker->openUntil('crm', new DateTimeImmutable('2026-09-15T10:00:29+00:00')))->not->toBeNull();
});

it('lets a trial attempt through once the cooldown has passed', function () {
    foreach (range(1, 5) as $ignored) {
        $this->breaker->recordFailure('crm', $this->now);
    }

    expect($this->breaker->openUntil('crm', new DateTimeImmutable('2026-09-15T10:00:30+00:00')))->toBeNull();
});

it('opens again right away if the trial attempt fails', function () {
    foreach (range(1, 5) as $ignored) {
        $this->breaker->recordFailure('crm', $this->now);
    }
    $afterCooldown = new DateTimeImmutable('2026-09-15T10:00:31+00:00');

    $this->breaker->recordFailure('crm', $afterCooldown);

    expect($this->breaker->openUntil('crm', $afterCooldown)?->format(DATE_ATOM))->toBe('2026-09-15T10:01:01+00:00');
});

it('closes and forgets past failures after a success', function () {
    foreach (range(1, 5) as $ignored) {
        $this->breaker->recordFailure('crm', $this->now);
    }

    $this->breaker->recordSuccess('crm');
    $this->breaker->recordFailure('crm', $this->now);

    expect($this->breaker->openUntil('crm', $this->now))->toBeNull();
});
