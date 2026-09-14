<?php

namespace Reformas\Leads\Lead\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidPhoneNumberError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un teléfono válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_phone_number';
    }

    public function publicMessage(): string
    {
        return 'El teléfono no es válido.';
    }
}
