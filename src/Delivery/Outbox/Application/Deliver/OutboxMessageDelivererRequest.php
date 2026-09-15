<?php

namespace Reformas\Delivery\Outbox\Application\Deliver;

final readonly class OutboxMessageDelivererRequest
{
    public function __construct(public string $messageId) {}
}
