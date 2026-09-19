<?php

namespace Reformas\Leads\Attachment\Application\Upload;

final readonly class AttachmentUploaderResponse
{
    public function __construct(
        public string $id,
        public string $fileName,
        public int $sizeBytes,
    ) {}

    /**
     * @return array{id: string, file_name: string, size_bytes: int}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'file_name' => $this->fileName, 'size_bytes' => $this->sizeBytes];
    }
}
