<?php

namespace Reformas\Leads\Lead\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidLeadSourceError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un origen válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_lead_source';
    }

    public function publicMessage(): string
    {
        return 'El origen del lead no es válido.';
    }
}
