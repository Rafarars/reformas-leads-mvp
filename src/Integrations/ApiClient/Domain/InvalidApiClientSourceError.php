<?php

namespace Reformas\Integrations\ApiClient\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidApiClientSourceError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un origen válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_api_client_source';
    }

    public function publicMessage(): string
    {
        return 'El origen debe ir en minúsculas, con números o guiones, entre 2 y 40 caracteres.';
    }
}
