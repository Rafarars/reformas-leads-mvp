<?php

namespace Reformas\Leads\Lead\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidLeadZoneError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es una zona válida', $value));
    }

    public function errorType(): string
    {
        return 'invalid_lead_zone';
    }

    public function publicMessage(): string
    {
        return 'La zona debe tener entre 2 y 120 caracteres.';
    }
}
