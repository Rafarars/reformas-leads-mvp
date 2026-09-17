<?php

namespace Reformas\Delivery\Outbox\Domain;

use DateInterval;
use DateTimeImmutable;

// Deja de golpear un servicio caído, con un circuito por destino: que caiga uno no frena a los demás.
// Tras varios fallos seguidos se abre y no se intenta nada hasta que
// pasa el enfriamiento; entonces deja pasar un intento de prueba. Si falla, se vuelve a abrir.
final readonly class CircuitBreaker
{
    public function __construct(
        private CircuitBreakerStore $store,
        private int $failureThreshold = 5,
        private int $cooldownSeconds = 30,
    ) {}

    public function openUntil(string $destination, DateTimeImmutable $now): ?DateTimeImmutable
    {
        $openUntil = $this->store->state($destination)->openUntil;

        return $openUntil !== null && $openUntil > $now ? $openUntil : null;
    }

    public function recordSuccess(string $destination): void
    {
        $this->store->save($destination, CircuitBreakerState::closed());
    }

    public function recordFailure(string $destination, DateTimeImmutable $now): void
    {
        $failures = $this->store->state($destination)->consecutiveFailures + 1;

        $openUntil = $failures >= $this->failureThreshold
            ? $now->add(new DateInterval('PT'.$this->cooldownSeconds.'S'))
            : null;

        $this->store->save($destination, new CircuitBreakerState($failures, $openUntil));
    }
}
