<?php

namespace Reformas\Leads\Classification\Domain;

use DateTimeImmutable;
use Reformas\Shared\Domain\DomainEvent;

// El lead ya se puede atender: clasificado por la IA o apartado para revisión humana. El aviso al
// equipo sale de aquí, nunca antes de clasificar.
final readonly class LeadClassifiedDomainEvent extends DomainEvent
{
    /**
     * @param  array<string, mixed>  $classification
     */
    public function __construct(
        string $aggregateId,
        DateTimeImmutable $occurredOn,
        private array $classification,
        private LeadContact $customer,
    ) {
        parent::__construct($aggregateId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'lead.classified';
    }

    public function body(): array
    {
        return [...$this->classification, 'customer' => $this->customer->toPrimitives()];
    }
}
