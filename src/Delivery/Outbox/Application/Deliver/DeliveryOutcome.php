<?php

namespace Reformas\Delivery\Outbox\Application\Deliver;

use DateTimeImmutable;

// Qué pasó con un intento y, si hay que volver, cuándo.
final readonly class DeliveryOutcome
{
    private function __construct(
        public DeliveryOutcomeType $type,
        public ?DateTimeImmutable $retryAt,
    ) {}

    public static function delivered(): self
    {
        return new self(DeliveryOutcomeType::Delivered, null);
    }

    public static function retryScheduled(DateTimeImmutable $at): self
    {
        return new self(DeliveryOutcomeType::RetryScheduled, $at);
    }

    public static function gaveUp(): self
    {
        return new self(DeliveryOutcomeType::GaveUp, null);
    }

    public static function circuitOpen(DateTimeImmutable $until): self
    {
        return new self(DeliveryOutcomeType::CircuitOpen, $until);
    }

    public static function skipped(): self
    {
        return new self(DeliveryOutcomeType::Skipped, null);
    }
}
