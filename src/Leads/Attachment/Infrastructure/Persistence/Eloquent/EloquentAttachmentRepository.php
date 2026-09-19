<?php

namespace Reformas\Leads\Attachment\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use DateTimeInterface;
use Reformas\Leads\Attachment\Domain\AttachmentId;
use Reformas\Leads\Attachment\Domain\AttachmentRepository;
use Reformas\Leads\Attachment\Domain\LeadAttachment;
use Reformas\Leads\Lead\Domain\LeadId;

final class EloquentAttachmentRepository implements AttachmentRepository
{
    public function save(LeadAttachment $attachment): void
    {
        $row = $attachment->toPrimitives();

        LeadAttachmentModel::query()->upsert([$row], ['id'], array_keys(array_diff_key($row, ['id' => true])));
    }

    public function search(AttachmentId $id): ?LeadAttachment
    {
        $model = LeadAttachmentModel::query()->find($id->value);

        return $model === null ? null : $this->toAttachment($model);
    }

    public function searchByLead(LeadId $leadId): array
    {
        $models = LeadAttachmentModel::query()
            ->where('lead_id', $leadId->value)
            ->orderBy('uploaded_at')
            ->orderBy('id')
            ->get();

        $attachments = [];

        foreach ($models as $model) {
            $attachments[] = $this->toAttachment($model);
        }

        return $attachments;
    }

    private function toAttachment(LeadAttachmentModel $model): LeadAttachment
    {
        return LeadAttachment::fromPrimitives([
            'id' => (string) $model->getAttribute('id'),
            'lead_id' => (string) $model->getAttribute('lead_id'),
            'kind' => (string) $model->getAttribute('kind'),
            'file_name' => (string) $model->getAttribute('file_name'),
            'content_type' => (string) $model->getAttribute('content_type'),
            'size_bytes' => (int) $model->getAttribute('size_bytes'),
            'path' => (string) $model->getAttribute('path'),
            'uploaded_at' => (new DateTimeImmutable((string) $model->getAttribute('uploaded_at')))->format(DateTimeInterface::ATOM),
        ]);
    }
}
