<?php

namespace Reformas\Shared\Domain;

interface Random
{
    public function between(int $min, int $max): int;
}
