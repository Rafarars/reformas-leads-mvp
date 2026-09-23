<?php

namespace Reformas\Notifications\TelegramLink\Domain;

final readonly class LinkToken
{
    // Exactamente 64 caracteres hexadecimales: el límite máximo admitido por /start en Telegram.
    private const PATTERN = '/^[a-f0-9]{64}$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidLinkTokenError;
        }
    }

    // SHA-256 determinista para indexación en base de datos sin guardar el token en claro.
    public function hash(): LinkTokenHash
    {
        return new LinkTokenHash(hash('sha256', $this->value));
    }
}
