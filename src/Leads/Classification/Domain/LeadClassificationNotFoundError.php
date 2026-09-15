<?php

namespace Reformas\Leads\Classification\Domain;

use Reformas\Shared\Domain\NotFoundError;

final class LeadClassificationNotFoundError extends NotFoundError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('No hay clasificación para el lead <%s>', $value));
    }

    public function errorType(): string
    {
        return 'lead_classification_not_found';
    }

    public function publicMessage(): string
    {
        return 'Ese lead todavía no tiene clasificación.';
    }
}
