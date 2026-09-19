<?php

namespace Reformas\Leads\Attachment\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidAttachmentSizeError extends InvalidArgumentError
{
    public function __construct(int $value)
    {
        parent::__construct(sprintf('Un adjunto de %d bytes no es válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_attachment_size';
    }

    public function publicMessage(): string
    {
        return 'El archivo debe pesar más de 0 y como máximo 10 MB.';
    }
}
