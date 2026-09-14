<?php

namespace Reformas\Leads\Lead\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidEmailAddressError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un email válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_email_address';
    }

    public function publicMessage(): string
    {
        return 'El email no es válido.';
    }
}
