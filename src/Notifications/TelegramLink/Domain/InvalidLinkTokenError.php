<?php

namespace Reformas\Notifications\TelegramLink\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidLinkTokenError extends InvalidArgumentError
{
    // El token en claro nunca se incluye en el mensaje para no dejar rastro en registros.
    public function __construct()
    {
        parent::__construct('The link token format is not valid.');
    }

    public function errorType(): string
    {
        return 'invalid_link_token';
    }

    public function publicMessage(): string
    {
        return 'El token de vinculación no es válido.';
    }
}
