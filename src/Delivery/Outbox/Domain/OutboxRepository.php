<?php

namespace Reformas\Delivery\Outbox\Domain;

interface OutboxRepository
{
    public function save(OutboxMessage $message): void;

    public function search(OutboxMessageId $id): ?OutboxMessage;
}
