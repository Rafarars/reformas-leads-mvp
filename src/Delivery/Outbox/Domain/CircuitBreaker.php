<?php

namespace Reformas\Delivery\Outbox\Domain;

use DateInterval;
use DateTimeImmutable;

// Deja de golpear un servicio caído. Tras varios fallos seguidos se abre y no se intenta nada hasta que
// pasa el enfriamiento; entonces deja pasar un intento de prueba. Si falla, se vuelve a abrir.
final readonly class CircuitBreaker
{
    public function __construct(
        private CircuitBreakerStore $store,
        private int $failureThreshold = 5,
        private int $cooldownSeconds = 30,
    ) {}

    public function openUntil(DateTimeImmutable $now): ?DateTimeImmutable
    {
        $openUntil = $this->store->state()->openUntil;

        return $openUntil !== null && $openUntil > $now ? $openUntil : null;
    }

    public function recordSuccess(): void
    {
        $this->store->save(CircuitBreakerState::closed());
    }

    public function recordFailure(DateTimeImmutable $now): void
    {
        $failures = $this->store->state()->consecutiveFailures + 1;

        $openUntil = $failures >= $this->failureThreshold
            ? $now->add(new DateInterval('PT'.$this->cooldownSeconds.'S'))
            : null;

        $this->store->save(new CircuitBreakerState($failures, $openUntil));
    }
}
