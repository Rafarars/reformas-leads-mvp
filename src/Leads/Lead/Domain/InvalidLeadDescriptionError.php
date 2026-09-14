<?php

namespace Reformas\Leads\Lead\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidLeadDescriptionError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('Descripción inválida de %d caracteres', mb_strlen($value)));
    }

    public function errorType(): string
    {
        return 'invalid_lead_description';
    }

    public function publicMessage(): string
    {
        return 'La descripción no puede estar vacía ni superar los 5000 caracteres.';
    }
}
