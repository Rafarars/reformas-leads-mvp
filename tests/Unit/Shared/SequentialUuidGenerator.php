<?php

namespace Tests\Unit\Shared;

use Reformas\Shared\Domain\UuidGenerator;

final class SequentialUuidGenerator implements UuidGenerator
{
    private int $next = 1;

    public function generate(): string
    {
        return sprintf('00000000-0000-7000-8000-%012d', $this->next++);
    }
}
