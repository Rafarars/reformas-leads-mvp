<?php

namespace Reformas\Leads\Attachment\Application\Download;

final readonly class AttachmentReaderRequest
{
    public function __construct(public string $attachmentId) {}
}
