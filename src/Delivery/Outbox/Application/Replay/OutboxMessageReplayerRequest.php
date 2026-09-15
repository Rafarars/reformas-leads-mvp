<?php

namespace Reformas\Delivery\Outbox\Application\Replay;

final readonly class OutboxMessageReplayerRequest
{
    public function __construct(public string $messageId) {}
}
