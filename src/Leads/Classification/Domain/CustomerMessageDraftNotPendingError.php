<?php

namespace Reformas\Leads\Classification\Domain;

use Reformas\Shared\Domain\ConflictError;

final class CustomerMessageDraftNotPendingError extends ConflictError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('El lead <%s> no tiene un borrador pendiente de aprobar', $value));
    }

    public function errorType(): string
    {
        return 'customer_message_draft_not_pending';
    }

    public function publicMessage(): string
    {
        return 'Este lead no tiene un borrador pendiente de aprobar.';
    }
}
