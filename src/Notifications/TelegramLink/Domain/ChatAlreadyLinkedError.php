<?php

namespace Reformas\Notifications\TelegramLink\Domain;

use Reformas\Shared\Domain\ConflictError;

final class ChatAlreadyLinkedError extends ConflictError
{
    public function __construct(string $chatId = '')
    {
        parent::__construct("The Telegram chat id '{$chatId}' is already linked to another user.");
    }

    public function errorType(): string
    {
        return 'chat_already_linked';
    }

    public function publicMessage(): string
    {
        return 'Esta cuenta de Telegram ya está vinculada a otro usuario.';
    }
}
