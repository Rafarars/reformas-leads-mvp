<?php

namespace Reformas\Notifications\TelegramLink\Domain;

use Reformas\Shared\Domain\ConflictError;

final class LinkTokenAlreadyUsedError extends ConflictError
{
    public function __construct()
    {
        parent::__construct('The link token has already been used.');
    }

    public function errorType(): string
    {
        return 'link_token_already_used';
    }

    public function publicMessage(): string
    {
        return 'Este enlace para vincular Telegram ya fue utilizado.';
    }
}
