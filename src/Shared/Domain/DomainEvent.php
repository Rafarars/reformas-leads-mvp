<?php

namespace Reformas\Shared\Domain;

use DateTimeImmutable;

// Algo que ya pasó en el dominio. No sabe quién lo escucha ni cómo se entrega.
abstract readonly class DomainEvent
{
    public function __construct(
        public string $aggregateId,
        public DateTimeImmutable $occurredOn,
    ) {}

    abstract public static function eventName(): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function body(): array;
}
