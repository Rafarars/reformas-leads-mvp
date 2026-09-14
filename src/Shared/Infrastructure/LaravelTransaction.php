<?php

namespace Reformas\Shared\Infrastructure;

use Illuminate\Support\Facades\DB;
use Reformas\Shared\Domain\Transaction;

final class LaravelTransaction implements Transaction
{
    public function run(callable $operation): mixed
    {
        return DB::transaction($operation(...));
    }
}
