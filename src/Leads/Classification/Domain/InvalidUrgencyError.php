<?php

namespace Reformas\Leads\Classification\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidUrgencyError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es una urgencia válida', $value));
    }

    public function errorType(): string
    {
        return 'invalid_urgency';
    }

    public function publicMessage(): string
    {
        return 'La urgencia no es válida.';
    }
}
