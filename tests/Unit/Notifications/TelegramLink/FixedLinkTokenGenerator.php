<?php

namespace Tests\Unit\Notifications\TelegramLink;

use Reformas\Notifications\TelegramLink\Domain\LinkToken;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenGenerator;

final class FixedLinkTokenGenerator implements LinkTokenGenerator
{
    public const TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function __construct(private readonly string $token = self::TOKEN) {}

    public function generate(): LinkToken
    {
        return new LinkToken($this->token);
    }
}
