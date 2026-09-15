<?php

namespace Reformas\Leads\Lead\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use Reformas\Shared\Domain\AggregateRoot;

/**
 * @phpstan-type LeadPrimitives array{
 *     id: string,
 *     name: string,
 *     phone: string,
 *     email: string,
 *     renovation_type: string,
 *     zone: ?string,
 *     budget: ?int,
 *     description: ?string,
 *     source: string,
 *     status: string,
 *     contact_count: int,
 *     received_at: string,
 *     last_contact_at: string
 * }
 */
final class Lead extends AggregateRoot
{
    /** @var list<LeadHistoryEntry> */
    private array $pendingHistory = [];

    private function __construct(
        private readonly LeadId $id,
        private readonly LeadName $name,
        private readonly PhoneNumber $phone,
        private readonly EmailAddress $email,
        private readonly RenovationType $renovationType,
        private ?LeadZone $zone,
        private ?Budget $budget,
        private ?LeadDescription $description,
        private readonly LeadSource $source,
        private LeadStatus $status,
        private int $contactCount,
        private readonly DateTimeImmutable $receivedAt,
        private DateTimeImmutable $lastContactAt,
    ) {}

    public static function receive(LeadId $id, LeadSubmission $submission, DateTimeImmutable $now): self
    {
        $lead = new self(
            $id,
            $submission->name,
            $submission->phone,
            $submission->email,
            $submission->renovationType,
            $submission->zone,
            $submission->budget,
            $submission->description,
            $submission->source,
            LeadStatus::Received,
            1,
            $now,
            $now,
        );

        $lead->record(LeadHistoryType::Received, $submission->source, $now);
        $lead->recordEvent(new LeadReceivedDomainEvent($id->value, $now, $lead->toPrimitives(), repeated: false));

        return $lead;
    }

    // Un segundo contacto no reescribe lo que ya se sabía: solo completa lo que faltaba.
    public function registerRepeatedContact(LeadSubmission $submission, DateTimeImmutable $now): void
    {
        $this->zone ??= $submission->zone;
        $this->budget ??= $submission->budget;
        $this->description ??= $submission->description;
        $this->contactCount++;
        $this->lastContactAt = $now;

        $this->record(LeadHistoryType::RepeatedContact, $submission->source, $now);
        $this->recordEvent(new LeadReceivedDomainEvent($this->id->value, $now, $this->toPrimitives(), repeated: true));
    }

    public function id(): LeadId
    {
        return $this->id;
    }

    /**
     * @return list<LeadHistoryEntry>
     */
    public function pullPendingHistory(): array
    {
        $pending = $this->pendingHistory;
        $this->pendingHistory = [];

        return $pending;
    }

    /**
     * @param  LeadPrimitives  $primitives
     */
    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            new LeadId($primitives['id']),
            new LeadName($primitives['name']),
            new PhoneNumber($primitives['phone']),
            new EmailAddress($primitives['email']),
            RenovationType::from($primitives['renovation_type']),
            $primitives['zone'] === null ? null : new LeadZone($primitives['zone']),
            $primitives['budget'] === null ? null : new Budget($primitives['budget']),
            $primitives['description'] === null ? null : new LeadDescription($primitives['description']),
            new LeadSource($primitives['source']),
            LeadStatus::from($primitives['status']),
            $primitives['contact_count'],
            new DateTimeImmutable($primitives['received_at']),
            new DateTimeImmutable($primitives['last_contact_at']),
        );
    }

    /**
     * @return LeadPrimitives
     */
    public function toPrimitives(): array
    {
        return [
            'id' => $this->id->value,
            'name' => $this->name->value,
            'phone' => $this->phone->value,
            'email' => $this->email->value,
            'renovation_type' => $this->renovationType->value,
            'zone' => $this->zone?->value,
            'budget' => $this->budget?->euros,
            'description' => $this->description?->value,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'contact_count' => $this->contactCount,
            'received_at' => $this->receivedAt->format(DateTimeInterface::ATOM),
            'last_contact_at' => $this->lastContactAt->format(DateTimeInterface::ATOM),
        ];
    }

    private function record(LeadHistoryType $type, LeadSource $source, DateTimeImmutable $now): void
    {
        $this->pendingHistory[] = new LeadHistoryEntry($type, $source, $now);
    }
}
