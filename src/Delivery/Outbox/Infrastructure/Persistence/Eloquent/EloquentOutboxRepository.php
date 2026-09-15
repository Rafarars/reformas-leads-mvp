<?php

namespace Reformas\Delivery\Outbox\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use DateTimeInterface;
use Reformas\Delivery\Outbox\Domain\OutboxMessage;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use UnexpectedValueException;

final class EloquentOutboxRepository implements OutboxRepository
{
    public function save(OutboxMessage $message): void
    {
        $row = $message->toPrimitives();
        $row['payload'] = json_encode($row['payload'], JSON_THROW_ON_ERROR);

        OutboxMessageModel::query()->upsert([$row], ['id'], array_keys(array_diff_key($row, ['id' => true])));
    }

    public function search(OutboxMessageId $id): ?OutboxMessage
    {
        $model = OutboxMessageModel::query()->find($id->value);

        return $model === null ? null : $this->toMessage($model);
    }

    public function searchDue(DateTimeImmutable $dueBefore, int $limit): array
    {
        $rows = OutboxMessageModel::query()
            ->where('status', 'pending')
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', $dueBefore->format(DateTimeInterface::ATOM))
            ->orderBy('next_attempt_at')
            ->limit($limit)
            ->get();

        $messages = [];

        foreach ($rows as $row) {
            $messages[] = $this->toMessage($row);
        }

        return $messages;
    }

    private function toMessage(OutboxMessageModel $model): OutboxMessage
    {
        $payload = json_decode((string) $model->getAttribute('payload'), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new UnexpectedValueException('El payload del mensaje del outbox no es un objeto JSON.');
        }

        $lastError = $model->getAttribute('last_error');

        return OutboxMessage::fromPrimitives([
            'id' => (string) $model->getAttribute('id'),
            'event_name' => (string) $model->getAttribute('event_name'),
            'aggregate_id' => (string) $model->getAttribute('aggregate_id'),
            'payload' => $payload,
            'status' => (string) $model->getAttribute('status'),
            'attempts' => (int) $model->getAttribute('attempts'),
            'next_attempt_at' => $this->nullableAtom($model->getAttribute('next_attempt_at')),
            'last_error' => $lastError === null ? null : (string) $lastError,
            'created_at' => (string) $this->nullableAtom($model->getAttribute('created_at')),
            'delivered_at' => $this->nullableAtom($model->getAttribute('delivered_at')),
        ]);
    }

    private function nullableAtom(mixed $value): ?string
    {
        return $value === null ? null : (new DateTimeImmutable((string) $value))->format(DateTimeInterface::ATOM);
    }
}
