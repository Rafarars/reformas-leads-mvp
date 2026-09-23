<?php

namespace Reformas\Notifications\TelegramLink\Infrastructure;

use Reformas\Notifications\TelegramLink\Domain\LinkToken;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenGenerator;

final class RandomLinkTokenGenerator implements LinkTokenGenerator
{
    public function generate(): LinkToken
    {
        return new LinkToken(bin2hex(random_bytes(32)));
    }
}
