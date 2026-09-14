<?php

namespace Reformas\Leads\Lead\Domain;

use DateTimeImmutable;
use DateTimeInterface;

final readonly class LeadHistoryEntry
{
    public function __construct(
        public LeadHistoryType $type,
        public LeadSource $source,
        public DateTimeImmutable $occurredAt,
    ) {}

    /**
     * @param  array{type: string, source: string, occurred_at: string}  $primitives
     */
    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            LeadHistoryType::from($primitives['type']),
            new LeadSource($primitives['source']),
            new DateTimeImmutable($primitives['occurred_at']),
        );
    }

    /**
     * @return array{type: string, source: string, occurred_at: string}
     */
    public function toPrimitives(): array
    {
        return [
            'type' => $this->type->value,
            'source' => $this->source->value,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
        ];
    }
}
