<?php

namespace Reformas\Notifications\TelegramLink\Application\Complete;

final readonly class CompleteLinkRequest
{
    public function __construct(
        public string $token,
        public string $chatId,
    ) {}
}
