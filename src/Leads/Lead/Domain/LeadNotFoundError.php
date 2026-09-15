<?php

namespace Reformas\Leads\Lead\Domain;

use Reformas\Shared\Domain\NotFoundError;

final class LeadNotFoundError extends NotFoundError
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('No existe el lead <%s>', $id));
    }

    public function errorType(): string
    {
        return 'lead_not_found';
    }

    public function publicMessage(): string
    {
        return 'El lead no existe.';
    }
}
