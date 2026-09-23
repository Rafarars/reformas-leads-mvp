<?php

namespace Reformas\Notifications\TelegramLink\Domain;

use Reformas\Shared\Domain\NotFoundError;

final class LinkTokenNotFoundError extends NotFoundError
{
    public function __construct()
    {
        parent::__construct('The link token was not found.');
    }

    public function errorType(): string
    {
        return 'link_token_not_found';
    }

    public function publicMessage(): string
    {
        return 'No se encontró ninguna solicitud de vinculación para este enlace.';
    }
}
