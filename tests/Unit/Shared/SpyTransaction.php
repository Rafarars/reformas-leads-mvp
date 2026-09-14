<?php

namespace Tests\Unit\Shared;

use Reformas\Shared\Domain\Transaction;

final class SpyTransaction implements Transaction
{
    public int $runs = 0;

    public function run(callable $operation): mixed
    {
        $this->runs++;

        return $operation();
    }
}
