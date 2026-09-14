<?php

namespace Reformas\Leads\Lead\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidRenovationTypeError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un tipo de reforma válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_renovation_type';
    }

    public function publicMessage(): string
    {
        return 'El tipo de reforma no es válido. Tipos admitidos: '.implode(', ', array_column(RenovationType::cases(), 'value')).'.';
    }
}
