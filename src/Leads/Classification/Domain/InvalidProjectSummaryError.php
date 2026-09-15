<?php

namespace Reformas\Leads\Classification\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidProjectSummaryError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un resumen válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_project_summary';
    }

    public function publicMessage(): string
    {
        return 'El resumen debe tener entre 1 y 300 caracteres.';
    }
}
