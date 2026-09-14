<?php

namespace Reformas\Shared\Domain;

interface Transaction
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function run(callable $operation): mixed;
}
