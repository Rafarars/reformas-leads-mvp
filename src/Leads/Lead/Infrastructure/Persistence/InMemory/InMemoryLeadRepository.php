<?php

namespace Reformas\Leads\Lead\Infrastructure\Persistence\InMemory;

use Reformas\Leads\Lead\Domain\EmailAddress;
use Reformas\Leads\Lead\Domain\Lead;
use Reformas\Leads\Lead\Domain\LeadHistoryEntry;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadRepository;
use Reformas\Leads\Lead\Domain\PhoneNumber;

/**
 * @phpstan-import-type LeadPrimitives from Lead
 */
final class InMemoryLeadRepository implements LeadRepository
{
    // Primitivas y no instancias: si guardara el objeto, la prueba y el código compartirían
    // la misma referencia y pasarían por accidente.
    /** @var array<string, LeadPrimitives> */
    private array $leads = [];

    /** @var array<string, list<array{type: string, source: string, occurred_at: string}>> */
    private array $history = [];

    public function save(Lead $lead): void
    {
        $primitives = $lead->toPrimitives();
        $this->leads[$primitives['id']] = $primitives;

        foreach ($lead->pullPendingHistory() as $entry) {
            $this->history[$primitives['id']][] = $entry->toPrimitives();
        }
    }

    public function search(LeadId $id): ?Lead
    {
        $primitives = $this->leads[$id->value] ?? null;

        return $primitives === null ? null : Lead::fromPrimitives($primitives);
    }

    public function searchByContact(PhoneNumber $phone, EmailAddress $email): ?Lead
    {
        foreach ($this->leads as $primitives) {
            if ($primitives['phone'] === $phone->value || $primitives['email'] === $email->value) {
                return Lead::fromPrimitives($primitives);
            }
        }

        return null;
    }

    public function history(LeadId $id): array
    {
        return array_map(
            static fn (array $entry): LeadHistoryEntry => LeadHistoryEntry::fromPrimitives($entry),
            $this->history[$id->value] ?? [],
        );
    }
}
