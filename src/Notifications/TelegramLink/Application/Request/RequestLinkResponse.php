<?php

namespace Reformas\Notifications\TelegramLink\Application\Request;

final readonly class RequestLinkResponse
{
    // El token en claro solo viaja aquí para construir el enlace t.me/<bot>?start=<token>.
    public function __construct(
        public string $token,
        public string $expiresAt,
    ) {}
}
