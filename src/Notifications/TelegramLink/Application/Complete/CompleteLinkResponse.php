<?php

namespace Reformas\Notifications\TelegramLink\Application\Complete;

final readonly class CompleteLinkResponse
{
    public function __construct(
        public int $userId,
        public string $chatId,
        public string $linkedAt,
    ) {}
}
