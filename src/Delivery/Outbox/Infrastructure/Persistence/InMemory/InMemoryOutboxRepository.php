<?php

namespace Reformas\Delivery\Outbox\Infrastructure\Persistence\InMemory;

use DateTimeImmutable;
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

    public function searchDue(DateTimeImmutable $dueBefore, int $limit): array
    {
        $due = array_filter(
            $this->messages,
            static fn (array $message): bool => $message['status'] === 'pending'
                && $message['next_attempt_at'] !== null
                && new DateTimeImmutable($message['next_attempt_at']) <= $dueBefore,
        );

        usort($due, static fn (array $a, array $b): int => strcmp((string) $a['next_attempt_at'], (string) $b['next_attempt_at']));

        return array_map(
            static fn (array $message): OutboxMessage => OutboxMessage::fromPrimitives($message),
            array_slice($due, 0, $limit),
        );
    }

    public function countByStatus(): array
    {
        $counts = ['pending' => 0, 'delivered' => 0, 'dead' => 0];

        foreach ($this->messages as $message) {
            match ($message['status']) {
                'pending' => $counts['pending']++,
                'delivered' => $counts['delivered']++,
                default => $counts['dead']++,
            };
        }

        return $counts;
    }

    public function searchLatest(int $limit): array
    {
        $messages = array_values($this->messages);

        usort($messages, static fn (array $a, array $b): int => [$b['created_at'], $b['id']] <=> [$a['created_at'], $a['id']]);

        return array_map(
            static fn (array $message): OutboxMessage => OutboxMessage::fromPrimitives($message),
            array_slice($messages, 0, $limit),
        );
    }
}
