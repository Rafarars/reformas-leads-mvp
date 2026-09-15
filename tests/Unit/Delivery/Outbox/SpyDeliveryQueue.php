<?php

namespace Tests\Unit\Delivery\Outbox;

use Reformas\Delivery\Outbox\Domain\DeliveryQueue;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;

final class SpyDeliveryQueue implements DeliveryQueue
{
    /** @var list<string> */
    public array $enqueued = [];

    public function enqueue(OutboxMessageId $id): void
    {
        $this->enqueued[] = $id->value;
    }
}
