<?php

namespace Reformas\Delivery\Outbox\Infrastructure\Persistence\InMemory;

use Reformas\Delivery\Outbox\Domain\OutboxMessage;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;

/**
 * @phpstan-import-type OutboxMessagePrimitives from OutboxMessage
 */
final class InMemoryOutboxRepository implements OutboxRepository
{
    /** @var array<string, OutboxMessagePrimitives> */
    private array $messages = [];

    public function save(OutboxMessage $message): void
    {
        $primitives = $message->toPrimitives();
        $this->messages[$primitives['id']] = $primitives;
    }

    public function search(OutboxMessageId $id): ?OutboxMessage
    {
        $primitives = $this->messages[$id->value] ?? null;

        return $primitives === null ? null : OutboxMessage::fromPrimitives($primitives);
    }
}
