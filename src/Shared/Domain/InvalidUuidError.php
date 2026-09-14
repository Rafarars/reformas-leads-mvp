<?php

namespace Reformas\Shared\Domain;

final class InvalidUuidError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un identificador válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_identifier';
    }

    public function publicMessage(): string
    {
        return 'El identificador no es válido.';
    }
}
