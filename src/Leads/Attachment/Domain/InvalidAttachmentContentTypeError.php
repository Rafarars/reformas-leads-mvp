<?php

namespace Reformas\Leads\Attachment\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidAttachmentContentTypeError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un formato admitido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_attachment_content_type';
    }

    public function publicMessage(): string
    {
        return 'Solo se admiten imágenes JPG, PNG o WEBP y archivos PDF.';
    }
}
