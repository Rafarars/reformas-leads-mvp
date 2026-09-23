<?php

namespace Reformas\Notifications\TelegramLink\Domain;

final readonly class LinkTokenHash
{
    private const PATTERN = '/^[a-f0-9]{64}$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidLinkTokenHashError($value);
        }
    }
}
