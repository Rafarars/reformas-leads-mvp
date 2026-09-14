<?php

namespace Reformas\Leads\Lead\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidBudgetError extends InvalidArgumentError
{
    public function __construct(int $value)
    {
        parent::__construct(sprintf('<%d> no es un presupuesto válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_budget';
    }

    public function publicMessage(): string
    {
        return 'El presupuesto debe ser un importe en euros mayor que cero.';
    }
}
