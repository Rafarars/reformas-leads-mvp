<?php

namespace Reformas\Delivery\Outbox\Domain;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * @phpstan-type OutboxMessagePrimitives array{
 *     id: string,
 *     event_name: string,
 *     aggregate_id: string,
 *     payload: array<string, mixed>,
 *     status: string,
 *     attempts: int,
 *     next_attempt_at: ?string,
 *     last_error: ?string,
 *     created_at: string,
 *     delivered_at: ?string,
 *     last_replayed_at: ?string
 * }
 */
final class OutboxMessage
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        private readonly OutboxMessageId $id,
        private readonly string $eventName,
        private readonly string $aggregateId,
        private readonly array $payload,
        private OutboxStatus $status,
        private int $attempts,
        private ?DateTimeImmutable $nextAttemptAt,
        private ?string $lastError,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $deliveredAt,
        private ?DateTimeImmutable $lastReplayedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function create(
        OutboxMessageId $id,
        string $eventName,
        string $aggregateId,
        array $payload,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $eventName, $aggregateId, $payload, OutboxStatus::Pending, 0, $now, null, $now, null, null);
    }

    public function id(): OutboxMessageId
    {
        return $this->id;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    // Desde cuándo cuenta el plazo para rendirse: un reintento manual abre una ventana nueva, o un
    // mensaje viejo se volvería a apartar al primer fallo.
    public function retryWindowStartedAt(): DateTimeImmutable
    {
        return $this->lastReplayedAt ?? $this->createdAt;
    }

    public function nextAttemptAt(): ?DateTimeImmutable
    {
        return $this->nextAttemptAt;
    }

    public function isDead(): bool
    {
        return $this->status === OutboxStatus::Dead;
    }

    public function isPending(): bool
    {
        return $this->status === OutboxStatus::Pending;
    }

    public function markDelivered(DateTimeImmutable $now): void
    {
        $this->status = OutboxStatus::Delivered;
        $this->attempts++;
        $this->nextAttemptAt = null;
        $this->lastError = null;
        $this->deliveredAt = $now;
    }

    public function recordFailedAttempt(string $error, DateTimeImmutable $nextAttemptAt): void
    {
        $this->attempts++;
        $this->lastError = $error;
        $this->nextAttemptAt = $nextAttemptAt;
    }

    public function markDead(string $error): void
    {
        $this->status = OutboxStatus::Dead;
        $this->attempts++;
        $this->lastError = $error;
        $this->nextAttemptAt = null;
    }

    // Volver a intentar un mensaje apartado, una vez arreglada la causa.
    public function replay(DateTimeImmutable $now): void
    {
        $this->status = OutboxStatus::Pending;
        $this->nextAttemptAt = $now;
        $this->lastReplayedAt = $now;
    }

    /**
     * @param  OutboxMessagePrimitives  $primitives
     */
    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            new OutboxMessageId($primitives['id']),
            $primitives['event_name'],
            $primitives['aggregate_id'],
            $primitives['payload'],
            OutboxStatus::from($primitives['status']),
            $primitives['attempts'],
            $primitives['next_attempt_at'] === null ? null : new DateTimeImmutable($primitives['next_attempt_at']),
            $primitives['last_error'],
            new DateTimeImmutable($primitives['created_at']),
            $primitives['delivered_at'] === null ? null : new DateTimeImmutable($primitives['delivered_at']),
            $primitives['last_replayed_at'] === null ? null : new DateTimeImmutable($primitives['last_replayed_at']),
        );
    }

    /**
     * @return OutboxMessagePrimitives
     */
    public function toPrimitives(): array
    {
        return [
            'id' => $this->id->value,
            'event_name' => $this->eventName,
            'aggregate_id' => $this->aggregateId,
            'payload' => $this->payload,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'next_attempt_at' => $this->nextAttemptAt?->format(DateTimeInterface::ATOM),
            'last_error' => $this->lastError,
            'created_at' => $this->createdAt->format(DateTimeInterface::ATOM),
            'delivered_at' => $this->deliveredAt?->format(DateTimeInterface::ATOM),
            'last_replayed_at' => $this->lastReplayedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
