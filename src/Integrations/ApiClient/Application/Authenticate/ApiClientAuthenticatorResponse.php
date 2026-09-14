<?php

namespace Reformas\Integrations\ApiClient\Application\Authenticate;

final readonly class ApiClientAuthenticatorResponse
{
    public function __construct(
        public string $id,
        public string $source,
    ) {}
}
