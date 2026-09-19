<?php

namespace Reformas\Leads\Attachment\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use Reformas\Leads\Lead\Domain\LeadId;

/**
 * @phpstan-type LeadAttachmentPrimitives array{
 *     id: string,
 *     lead_id: string,
 *     kind: string,
 *     file_name: string,
 *     content_type: string,
 *     size_bytes: int,
 *     path: string,
 *     uploaded_at: string
 * }
 */
final class LeadAttachment
{
    private function __construct(
        private readonly AttachmentId $id,
        private readonly LeadId $leadId,
        private readonly AttachmentKind $kind,
        private readonly AttachmentFileName $fileName,
        private readonly AttachmentContentType $contentType,
        private readonly AttachmentSize $size,
        private readonly string $path,
        private readonly DateTimeImmutable $uploadedAt,
    ) {}

    public static function upload(
        AttachmentId $id,
        LeadId $leadId,
        AttachmentKind $kind,
        AttachmentFileName $fileName,
        AttachmentContentType $contentType,
        AttachmentSize $size,
        DateTimeImmutable $now,
    ): self {
        // La ruta la decide el dominio a partir de identificadores, nunca del nombre que envía quien sube.
        $path = sprintf('leads/%s/%s.%s', $leadId->value, $id->value, $contentType->extension());

        return new self($id, $leadId, $kind, $fileName, $contentType, $size, $path, $now);
    }

    public function id(): AttachmentId
    {
        return $this->id;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function fileName(): AttachmentFileName
    {
        return $this->fileName;
    }

    public function contentType(): AttachmentContentType
    {
        return $this->contentType;
    }

    /**
     * @param  LeadAttachmentPrimitives  $primitives
     */
    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            new AttachmentId($primitives['id']),
            new LeadId($primitives['lead_id']),
            AttachmentKind::from($primitives['kind']),
            new AttachmentFileName($primitives['file_name']),
            new AttachmentContentType($primitives['content_type']),
            new AttachmentSize($primitives['size_bytes']),
            $primitives['path'],
            new DateTimeImmutable($primitives['uploaded_at']),
        );
    }

    /**
     * @return LeadAttachmentPrimitives
     */
    public function toPrimitives(): array
    {
        return [
            'id' => $this->id->value,
            'lead_id' => $this->leadId->value,
            'kind' => $this->kind->value,
            'file_name' => $this->fileName->value,
            'content_type' => $this->contentType->value,
            'size_bytes' => $this->size->bytes,
            'path' => $this->path,
            'uploaded_at' => $this->uploadedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
