<?php

namespace Reformas\Leads\Classification\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use DateTimeInterface;
use Reformas\Leads\Classification\Domain\ClassificationStatus;
use Reformas\Leads\Classification\Domain\LeadClassification;
use Reformas\Leads\Classification\Domain\LeadClassificationRepository;
use Reformas\Leads\Lead\Domain\LeadId;

final class EloquentLeadClassificationRepository implements LeadClassificationRepository
{
    public function save(LeadClassification $classification): void
    {
        $row = $classification->toPrimitives();
        $row['missing_information'] = $this->encodeList($row['missing_information']);
        $row['contradictions'] = $this->encodeList($row['contradictions']);

        LeadClassificationModel::query()->upsert([$row], ['lead_id'], array_keys(array_diff_key($row, ['lead_id' => true, 'created_at' => true])));
    }

    public function search(LeadId $leadId): ?LeadClassification
    {
        $model = LeadClassificationModel::query()->find($leadId->value);

        return $model === null ? null : $this->toClassification($model);
    }

    public function searchLatest(int $limit): array
    {
        $models = LeadClassificationModel::query()
            ->orderByRaw('case when status = ? then 0 else 1 end', [ClassificationStatus::NeedsReview->value])
            ->orderByDesc('updated_at')
            ->orderByDesc('lead_id')
            ->limit($limit)
            ->get();

        $classifications = [];

        foreach ($models as $model) {
            $classifications[] = $this->toClassification($model);
        }

        return $classifications;
    }

    private function toClassification(LeadClassificationModel $model): LeadClassification
    {
        $confidence = $model->getAttribute('confidence');

        return LeadClassification::fromPrimitives([
            'lead_id' => (string) $model->getAttribute('lead_id'),
            'status' => (string) $model->getAttribute('status'),
            'category' => $this->nullableString($model->getAttribute('category')),
            'urgency' => $this->nullableString($model->getAttribute('urgency')),
            'confidence' => $confidence === null ? null : (float) $confidence,
            'reason' => $this->nullableString($model->getAttribute('reason')),
            'summary' => $this->nullableString($model->getAttribute('summary')),
            'missing_information' => $this->decodeList($model->getAttribute('missing_information')),
            'contradictions' => $this->decodeList($model->getAttribute('contradictions')),
            'draft_message' => $this->nullableString($model->getAttribute('draft_message')),
            'draft_status' => $this->nullableString($model->getAttribute('draft_status')),
            'draft_approved_by' => $this->nullableString($model->getAttribute('draft_approved_by')),
            'draft_approved_at' => $model->getAttribute('draft_approved_at') === null ? null : $this->toAtom($model->getAttribute('draft_approved_at')),
            'model' => $this->nullableString($model->getAttribute('model')),
            'review_reason' => $this->nullableString($model->getAttribute('review_reason')),
            'reviewed_by' => $this->nullableString($model->getAttribute('reviewed_by')),
            'created_at' => $this->toAtom($model->getAttribute('created_at')),
            'updated_at' => $this->toAtom($model->getAttribute('updated_at')),
        ]);
    }

    /**
     * @param  ?list<string>  $items
     */
    private function encodeList(?array $items): ?string
    {
        return $items === null ? null : (string) json_encode($items, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return ?list<string>
     */
    private function decodeList(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $items = json_decode((string) $value, true);

        return is_array($items) ? array_values(array_map(strval(...), $items)) : [];
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function toAtom(mixed $value): string
    {
        return (new DateTimeImmutable((string) $value))->format(DateTimeInterface::ATOM);
    }
}
