<?php

namespace Reformas\Delivery\Outbox\Domain;

use Reformas\Shared\Domain\ConflictError;

final class OutboxMessageNotDeadError extends ConflictError
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('El mensaje del outbox <%s> no está apartado', $id));
    }

    public function errorType(): string
    {
        return 'outbox_message_not_dead';
    }

    public function publicMessage(): string
    {
        return 'Solo se pueden reintentar los mensajes apartados.';
    }
}
