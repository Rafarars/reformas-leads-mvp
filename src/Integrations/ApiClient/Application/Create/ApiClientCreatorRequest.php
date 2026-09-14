<?php

namespace Reformas\Integrations\ApiClient\Application\Create;

final readonly class ApiClientCreatorRequest
{
    public function __construct(
        public string $name,
        public string $source,
    ) {}
}
