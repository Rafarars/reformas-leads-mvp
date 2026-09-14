<?php

namespace Reformas\Leads\Lead\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidLeadNameError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un nombre válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_lead_name';
    }

    public function publicMessage(): string
    {
        return 'El nombre debe tener entre 2 y 120 caracteres.';
    }
}
