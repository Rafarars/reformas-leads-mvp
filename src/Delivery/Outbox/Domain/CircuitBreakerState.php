<?php

namespace Reformas\Delivery\Outbox\Domain;

use DateTimeImmutable;

final readonly class CircuitBreakerState
{
    public function __construct(
        public int $consecutiveFailures,
        public ?DateTimeImmutable $openUntil,
    ) {}

    public static function closed(): self
    {
        return new self(0, null);
    }
}
