<?php

namespace Reformas\Leads\Lead\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use DateTimeInterface;
use Reformas\Leads\Lead\Domain\EmailAddress;
use Reformas\Leads\Lead\Domain\Lead;
use Reformas\Leads\Lead\Domain\LeadHistoryEntry;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadRepository;
use Reformas\Leads\Lead\Domain\PhoneNumber;

final class EloquentLeadRepository implements LeadRepository
{
    public function save(Lead $lead): void
    {
        $row = $lead->toPrimitives();

        LeadModel::query()->upsert([$row], ['id'], array_keys(array_diff_key($row, ['id' => true])));

        $history = array_map(
            static fn (LeadHistoryEntry $entry): array => ['lead_id' => $row['id'], ...$entry->toPrimitives()],
            $lead->pullPendingHistory(),
        );

        if ($history !== []) {
            LeadHistoryModel::query()->insert($history);
        }
    }

    public function search(LeadId $id): ?Lead
    {
        $model = LeadModel::query()->find($id->value);

        return $model === null ? null : $this->toLead($model);
    }

    public function searchByContact(PhoneNumber $phone, EmailAddress $email): ?Lead
    {
        $model = LeadModel::query()
            ->where('phone', $phone->value)
            ->orWhere('email', $email->value)
            ->orderBy('received_at')
            ->first();

        return $model === null ? null : $this->toLead($model);
    }

    public function history(LeadId $id): array
    {
        $rows = LeadHistoryModel::query()
            ->where('lead_id', $id->value)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $entries = [];

        foreach ($rows as $row) {
            $entries[] = LeadHistoryEntry::fromPrimitives([
                'type' => (string) $row->getAttribute('type'),
                'source' => (string) $row->getAttribute('source'),
                'occurred_at' => $this->toAtom($row->getAttribute('occurred_at')),
            ]);
        }

        return $entries;
    }

    private function toLead(LeadModel $model): Lead
    {
        $budget = $model->getAttribute('budget');

        return Lead::fromPrimitives([
            'id' => (string) $model->getAttribute('id'),
            'name' => (string) $model->getAttribute('name'),
            'phone' => (string) $model->getAttribute('phone'),
            'email' => (string) $model->getAttribute('email'),
            'renovation_type' => (string) $model->getAttribute('renovation_type'),
            'zone' => $this->nullableString($model->getAttribute('zone')),
            'budget' => $budget === null ? null : (int) $budget,
            'description' => $this->nullableString($model->getAttribute('description')),
            'source' => (string) $model->getAttribute('source'),
            'status' => (string) $model->getAttribute('status'),
            'contact_count' => (int) $model->getAttribute('contact_count'),
            'received_at' => $this->toAtom($model->getAttribute('received_at')),
            'last_contact_at' => $this->toAtom($model->getAttribute('last_contact_at')),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    // Cada motor devuelve las fechas en su formato ("2026-09-14 10:00:00+00" en PostgreSQL):
    // se normalizan para que el dominio reciba siempre lo mismo.
    private function toAtom(mixed $value): string
    {
        return (new DateTimeImmutable((string) $value))->format(DateTimeInterface::ATOM);
    }
}
