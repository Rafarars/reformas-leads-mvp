<?php

namespace Reformas\Leads\Classification\Infrastructure\Persistence\InMemory;

use Reformas\Leads\Classification\Domain\ClassificationStatus;
use Reformas\Leads\Classification\Domain\LeadClassification;
use Reformas\Leads\Classification\Domain\LeadClassificationRepository;
use Reformas\Leads\Lead\Domain\LeadId;

/**
 * @phpstan-import-type LeadClassificationPrimitives from LeadClassification
 */
final class InMemoryLeadClassificationRepository implements LeadClassificationRepository
{
    /** @var array<string, LeadClassificationPrimitives> */
    private array $classifications = [];

    public function save(LeadClassification $classification): void
    {
        $primitives = $classification->toPrimitives();
        $this->classifications[$primitives['lead_id']] = $primitives;
    }

    public function search(LeadId $leadId): ?LeadClassification
    {
        $primitives = $this->classifications[$leadId->value] ?? null;

        return $primitives === null ? null : LeadClassification::fromPrimitives($primitives);
    }

    public function searchLatest(int $limit): array
    {
        $all = array_values($this->classifications);

        usort($all, static fn (array $a, array $b): int => [
            $a['status'] === ClassificationStatus::NeedsReview->value ? 0 : 1, $b['updated_at'], $b['lead_id'],
        ] <=> [
            $b['status'] === ClassificationStatus::NeedsReview->value ? 0 : 1, $a['updated_at'], $a['lead_id'],
        ]);

        return array_map(
            static fn (array $primitives): LeadClassification => LeadClassification::fromPrimitives($primitives),
            array_slice($all, 0, $limit),
        );
    }
}
