<?php

namespace Reformas\Delivery\Outbox\Infrastructure\Queue;

use Reformas\Delivery\Outbox\Domain\DeliveryQueue;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;

final class LaravelDeliveryQueue implements DeliveryQueue
{
    public function enqueue(OutboxMessageId $id): void
    {
        // Después del commit: si la transacción se revierte, no sale ningún trabajo hacia un mensaje que no existe.
        // Fuera de una transacción se despacha en el acto.
        DeliverOutboxMessageJob::dispatch($id->value)->afterCommit();
    }
}
