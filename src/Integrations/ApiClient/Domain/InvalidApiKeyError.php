<?php

namespace Reformas\Integrations\ApiClient\Domain;

use Reformas\Shared\Domain\UnauthorizedError;

final class InvalidApiKeyError extends UnauthorizedError
{
    public function __construct()
    {
        parent::__construct('API key desconocida, con formato inválido o de un cliente desactivado');
    }

    public function errorType(): string
    {
        return 'invalid_api_key';
    }

    public function publicMessage(): string
    {
        return 'La API key no es válida.';
    }
}
