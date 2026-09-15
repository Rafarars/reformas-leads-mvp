<?php

namespace Reformas\Delivery\Outbox\Domain;

interface DeliveryQueue
{
    public function enqueue(OutboxMessageId $id): void;
}
