<?php

namespace Reformas\Leads\Lead\Domain;

use DateTimeImmutable;
use Reformas\Shared\Domain\DomainEvent;

final readonly class LeadReceivedDomainEvent extends DomainEvent
{
    /**
     * @param  array<string, mixed>  $lead
     */
    public function __construct(
        string $aggregateId,
        DateTimeImmutable $occurredOn,
        private array $lead,
        private bool $repeated,
    ) {
        parent::__construct($aggregateId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'lead.received';
    }

    public function body(): array
    {
        return [...$this->lead, 'repeated' => $this->repeated];
    }
}
