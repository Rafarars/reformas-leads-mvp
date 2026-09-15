<?php

namespace Reformas\Leads\Classification\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidClassificationReasonError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un motivo válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_classification_reason';
    }

    public function publicMessage(): string
    {
        return 'El motivo debe tener entre 1 y 500 caracteres.';
    }
}
