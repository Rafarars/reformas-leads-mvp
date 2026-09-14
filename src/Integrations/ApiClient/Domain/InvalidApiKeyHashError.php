<?php

namespace Reformas\Integrations\ApiClient\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidApiKeyHashError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('Hash de clave con formato inválido de %d caracteres', strlen($value)));
    }

    public function errorType(): string
    {
        return 'invalid_api_key_hash';
    }

    public function publicMessage(): string
    {
        return 'La clave almacenada no es válida.';
    }
}
