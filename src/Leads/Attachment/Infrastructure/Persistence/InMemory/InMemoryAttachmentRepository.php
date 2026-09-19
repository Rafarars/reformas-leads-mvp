<?php

namespace Reformas\Leads\Attachment\Infrastructure\Persistence\InMemory;

use Reformas\Leads\Attachment\Domain\AttachmentId;
use Reformas\Leads\Attachment\Domain\AttachmentRepository;
use Reformas\Leads\Attachment\Domain\LeadAttachment;
use Reformas\Leads\Lead\Domain\LeadId;

/**
 * @phpstan-import-type LeadAttachmentPrimitives from LeadAttachment
 */
final class InMemoryAttachmentRepository implements AttachmentRepository
{
    /** @var array<string, LeadAttachmentPrimitives> */
    private array $attachments = [];

    public function save(LeadAttachment $attachment): void
    {
        $primitives = $attachment->toPrimitives();
        $this->attachments[$primitives['id']] = $primitives;
    }

    public function search(AttachmentId $id): ?LeadAttachment
    {
        $primitives = $this->attachments[$id->value] ?? null;

        return $primitives === null ? null : LeadAttachment::fromPrimitives($primitives);
    }

    public function searchByLead(LeadId $leadId): array
    {
        $rows = array_values(array_filter(
            $this->attachments,
            static fn (array $attachment): bool => $attachment['lead_id'] === $leadId->value,
        ));

        usort($rows, static fn (array $a, array $b): int => [$a['uploaded_at'], $a['id']] <=> [$b['uploaded_at'], $b['id']]);

        return array_map(
            static fn (array $attachment): LeadAttachment => LeadAttachment::fromPrimitives($attachment),
            $rows,
        );
    }
}
