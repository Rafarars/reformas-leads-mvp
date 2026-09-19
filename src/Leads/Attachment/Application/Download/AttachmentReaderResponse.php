<?php

namespace Reformas\Leads\Attachment\Application\Download;

final readonly class AttachmentReaderResponse
{
    public function __construct(
        public string $contents,
        public string $fileName,
        public string $contentType,
    ) {}
}
