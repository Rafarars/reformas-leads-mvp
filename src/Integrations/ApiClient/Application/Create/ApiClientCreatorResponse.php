<?php

namespace Reformas\Integrations\ApiClient\Application\Create;

final readonly class ApiClientCreatorResponse
{
    public function __construct(
        public string $id,
        public string $apiKey,
    ) {}
}
