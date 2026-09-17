<?php

namespace Reformas\Delivery\Outbox\Infrastructure\Cache;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Reformas\Delivery\Outbox\Domain\CircuitBreakerState;
use Reformas\Delivery\Outbox\Domain\CircuitBreakerStore;

// En la caché compartida: todos los workers ven el mismo circuito.
final class CacheCircuitBreakerStore implements CircuitBreakerStore
{
    private const KEY = 'delivery:circuit-breaker:';

    public function state(string $destination): CircuitBreakerState
    {
        $stored = Cache::get(self::KEY.$destination);

        if (! is_array($stored)) {
            return CircuitBreakerState::closed();
        }

        $openUntil = $stored['open_until'] ?? null;

        return new CircuitBreakerState(
            (int) ($stored['failures'] ?? 0),
            is_string($openUntil) ? new DateTimeImmutable($openUntil) : null,
        );
    }

    public function save(string $destination, CircuitBreakerState $state): void
    {
        Cache::forever(self::KEY.$destination, [
            'failures' => $state->consecutiveFailures,
            'open_until' => $state->openUntil?->format(DateTimeInterface::ATOM),
        ]);
    }
}
