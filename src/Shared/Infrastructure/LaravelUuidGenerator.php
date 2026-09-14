<?php

namespace Reformas\Shared\Infrastructure;

use Illuminate\Support\Str;
use Reformas\Shared\Domain\UuidGenerator;

final class LaravelUuidGenerator implements UuidGenerator
{
    public function generate(): string
    {
        // UUIDv7 ordena por tiempo: los índices de la base no se fragmentan con cada inserción.
        return Str::uuid7()->toString();
    }
}
