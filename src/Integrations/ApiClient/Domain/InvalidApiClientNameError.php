<?php

namespace Reformas\Integrations\ApiClient\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidApiClientNameError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un nombre de cliente válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_api_client_name';
    }

    public function publicMessage(): string
    {
        return 'El nombre del cliente debe tener entre 2 y 80 caracteres.';
    }
}
