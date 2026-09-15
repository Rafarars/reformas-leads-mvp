<?php

namespace Reformas\Delivery\Outbox\Domain;

use DateTimeImmutable;

interface OutboxRepository
{
    public function save(OutboxMessage $message): void;

    public function search(OutboxMessageId $id): ?OutboxMessage;

    // Pendientes cuyo próximo intento ya debió ocurrir antes de esa fecha, los más atrasados primero.
    /**
     * @return list<OutboxMessage>
     */
    public function searchDue(DateTimeImmutable $dueBefore, int $limit): array;

    /**
     * @return array{pending: int, delivered: int, dead: int}
     */
    public function countByStatus(): array;

    // Los más recientes primero.
    /**
     * @return list<OutboxMessage>
     */
    public function searchLatest(int $limit): array;
}
