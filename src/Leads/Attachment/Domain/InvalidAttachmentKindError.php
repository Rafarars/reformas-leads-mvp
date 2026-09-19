<?php

namespace Reformas\Leads\Attachment\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidAttachmentKindError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un tipo de adjunto válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_attachment_kind';
    }

    public function publicMessage(): string
    {
        return 'El adjunto debe ser una foto o un plano.';
    }
}
