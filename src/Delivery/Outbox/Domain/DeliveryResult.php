<?php

namespace Reformas\Delivery\Outbox\Domain;

final readonly class DeliveryResult
{
    private function __construct(
        public bool $succeeded,
        public ?string $failureReason,
    ) {}

    public static function succeeded(): self
    {
        return new self(true, null);
    }

    public static function failed(string $reason): self
    {
        return new self(false, $reason);
    }
}
