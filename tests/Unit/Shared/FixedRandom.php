<?php

namespace Tests\Unit\Shared;

use Reformas\Shared\Domain\Random;

// Devuelve siempre el extremo pedido: permite comprobar los límites del jitter sin azar.
final readonly class FixedRandom implements Random
{
    public function __construct(private bool $alwaysMax = false) {}

    public function between(int $min, int $max): int
    {
        return $this->alwaysMax ? $max : $min;
    }
}
