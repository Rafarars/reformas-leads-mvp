<?php

namespace Reformas\Notifications\TelegramLink\Domain;

final readonly class TelegramChatId
{
    // Acepta enteros positivos (chats privados) y negativos (canales/supergrupos).
    private const PATTERN = '/^-?\d{1,20}$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidTelegramChatIdError($value);
        }
    }
}
