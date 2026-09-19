<?php

namespace Reformas\Leads\Attachment\Application\Search;

use Reformas\Leads\Attachment\Domain\AttachmentRepository;
use Reformas\Leads\Attachment\Domain\LeadAttachment;
use Reformas\Leads\Lead\Domain\LeadId;

final readonly class LeadAttachmentsSearcher
{
    public function __construct(private AttachmentRepository $attachments) {}

    /**
     * @return list<array{id: string, kind: string, file_name: string, content_type: string, size_bytes: int, uploaded_at: string}>
     */
    public function __invoke(string $leadId): array
    {
        return array_map(
            static function (LeadAttachment $attachment): array {
                $primitives = $attachment->toPrimitives();

                // La ruta en disco no sale del backend: fuera solo circula el identificador.
                return [
                    'id' => $primitives['id'],
                    'kind' => $primitives['kind'],
                    'file_name' => $primitives['file_name'],
                    'content_type' => $primitives['content_type'],
                    'size_bytes' => $primitives['size_bytes'],
                    'uploaded_at' => $primitives['uploaded_at'],
                ];
            },
            $this->attachments->searchByLead(new LeadId($leadId)),
        );
    }
}
