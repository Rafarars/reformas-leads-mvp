<?php

namespace Reformas\Leads\Classification\Domain;

use DateTimeImmutable;
use Reformas\Shared\Domain\DomainEvent;

// Una persona aprobó el mensaje: a partir de aquí un canal externo (n8n) puede enviarlo.
final readonly class CustomerMessageApprovedDomainEvent extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        DateTimeImmutable $occurredOn,
        private string $message,
        private string $approvedBy,
        private LeadContact $customer,
    ) {
        parent::__construct($aggregateId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'lead.customer_message_approved';
    }

    public function body(): array
    {
        return [
            'lead_id' => $this->aggregateId,
            'message' => $this->message,
            'approved_by' => $this->approvedBy,
            'customer' => $this->customer->toPrimitives(),
        ];
    }
}
