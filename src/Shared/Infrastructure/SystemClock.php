<?php

namespace Reformas\Shared\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Reformas\Shared\Domain\Clock;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
