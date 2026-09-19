<?php

namespace Reformas\Leads\Attachment\Application\Upload;

final readonly class AttachmentUploaderRequest
{
    public function __construct(
        public string $leadId,
        public string $kind,
        public string $fileName,
        public string $contentType,
        public int $sizeBytes,
        public string $contents,
    ) {}
}
