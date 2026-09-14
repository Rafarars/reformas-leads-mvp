<?php

namespace Reformas\Shared\Domain;

interface UuidGenerator
{
    public function generate(): string;
}
