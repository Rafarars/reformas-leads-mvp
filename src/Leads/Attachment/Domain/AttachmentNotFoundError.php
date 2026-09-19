<?php

namespace Reformas\Leads\Attachment\Domain;

use Reformas\Shared\Domain\NotFoundError;

final class AttachmentNotFoundError extends NotFoundError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('No existe el adjunto <%s>', $value));
    }

    public function errorType(): string
    {
        return 'attachment_not_found';
    }

    public function publicMessage(): string
    {
        return 'El adjunto no existe.';
    }
}
