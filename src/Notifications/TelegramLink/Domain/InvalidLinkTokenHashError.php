<?php

namespace Reformas\Notifications\TelegramLink\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidLinkTokenHashError extends InvalidArgumentError
{
    public function __construct(string $hash = '')
    {
        parent::__construct("The link token hash '{$hash}' is not a valid 64-character hex string.");
    }

    public function errorType(): string
    {
        return 'invalid_link_token_hash';
    }

    public function publicMessage(): string
    {
        return 'El hash del token de vinculación no es válido.';
    }
}
