<?php

namespace Reformas\Leads\Attachment\Domain;

final readonly class AttachmentSize
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(public int $bytes)
    {
        if ($bytes <= 0 || $bytes > self::MAX_BYTES) {
            throw new InvalidAttachmentSizeError($bytes);
        }
    }
}
