<?php

namespace Reformas\Leads\Attachment\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidAttachmentFileNameError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es un nombre de archivo válido', $value));
    }

    public function errorType(): string
    {
        return 'invalid_attachment_file_name';
    }

    public function publicMessage(): string
    {
        return 'El nombre del archivo debe tener entre 1 y 200 caracteres.';
    }
}
