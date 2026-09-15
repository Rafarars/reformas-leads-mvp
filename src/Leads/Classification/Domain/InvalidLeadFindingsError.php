<?php

namespace Reformas\Leads\Classification\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidLeadFindingsError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es una lista de hallazgos válida', $value));
    }

    public function errorType(): string
    {
        return 'invalid_lead_findings';
    }

    public function publicMessage(): string
    {
        return 'Como máximo 5 hallazgos de hasta 200 caracteres.';
    }
}
