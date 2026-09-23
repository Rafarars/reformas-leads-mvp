<?php

namespace Reformas\Notifications\TelegramLink\Application\Forget;

final readonly class ForgetLinkRequest
{
    public function __construct(public int $userId) {}
}
