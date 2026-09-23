<?php

namespace Reformas\Notifications\TelegramLink\Application\Request;

final readonly class RequestLinkRequest
{
    public function __construct(public int $userId) {}
}
