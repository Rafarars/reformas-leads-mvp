<?php

namespace Reformas\Notifications\TelegramLink\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidTelegramChatIdError extends InvalidArgumentError
{
    public function __construct(string $chatId = '')
    {
        parent::__construct("The Telegram chat id '{$chatId}' is not valid.");
    }

    public function errorType(): string
    {
        return 'invalid_telegram_chat_id';
    }

    public function publicMessage(): string
    {
        return 'El identificador de chat de Telegram no es válido.';
    }
}
