<?php

namespace Tests\Unit\Shared;

use DateTimeImmutable;
use Reformas\Shared\Domain\Clock;

final class FixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $now = new DateTimeImmutable('2026-09-14T10:00:00+00:00')) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
