<?php

namespace Reformas\Integrations\ApiClient\Application\Authenticate;

final readonly class ApiClientAuthenticatorRequest
{
    public function __construct(public string $apiKey) {}
}
