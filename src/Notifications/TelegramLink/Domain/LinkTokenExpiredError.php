<?php

namespace Reformas\Notifications\TelegramLink\Domain;

use Reformas\Shared\Domain\ConflictError;

final class LinkTokenExpiredError extends ConflictError
{
    public function __construct()
    {
        parent::__construct('The link token has expired.');
    }

    public function errorType(): string
    {
        return 'link_token_expired';
    }

    public function publicMessage(): string
    {
        return 'El enlace para vincular Telegram ha caducado. Solicita uno nuevo desde los ajustes.';
    }
}
