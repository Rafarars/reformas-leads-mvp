<?php

namespace Reformas\Integrations\ApiClient\Domain;

// Canal que el cliente representa ("web-form", "facebook-ads"). Cada lead hereda el origen de
// quien lo envió, nunca uno escrito en el cuerpo de la petición.
final readonly class ApiClientSource
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]{1,39}$/', $value) !== 1) {
            throw new InvalidApiClientSourceError($value);
        }
    }
}
