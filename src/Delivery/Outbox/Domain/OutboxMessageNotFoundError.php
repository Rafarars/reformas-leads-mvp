<?php

namespace Reformas\Delivery\Outbox\Domain;

use Reformas\Shared\Domain\NotFoundError;

final class OutboxMessageNotFoundError extends NotFoundError
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('El mensaje del outbox <%s> no existe', $id));
    }

    public function errorType(): string
    {
        return 'outbox_message_not_found';
    }

    public function publicMessage(): string
    {
        return 'El mensaje no existe.';
    }
}
