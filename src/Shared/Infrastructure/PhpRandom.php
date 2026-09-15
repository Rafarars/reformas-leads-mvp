<?php

namespace Reformas\Shared\Infrastructure;

use Reformas\Shared\Domain\Random;

final class PhpRandom implements Random
{
    public function between(int $min, int $max): int
    {
        return random_int($min, $max);
    }
}
