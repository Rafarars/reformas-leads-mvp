<?php

namespace Reformas\Leads\Attachment\Domain;

use Reformas\Leads\Lead\Domain\LeadId;

interface AttachmentRepository
{
    public function save(LeadAttachment $attachment): void;

    public function search(AttachmentId $id): ?LeadAttachment;

    // De la más antigua a la más reciente: se subieron en ese orden.
    /**
     * @return list<LeadAttachment>
     */
    public function searchByLead(LeadId $leadId): array;
}
