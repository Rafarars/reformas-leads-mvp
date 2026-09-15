<?php

namespace Reformas\Leads\Classification\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidCustomerMessageDraftError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un mensaje válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_customer_message_draft';
    }

    public function publicMessage(): string
    {
        return 'El mensaje debe tener entre 1 y 1500 caracteres.';
    }
}
