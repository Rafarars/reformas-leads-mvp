<?php

namespace Reformas\Integrations\ApiClient\Domain;

final readonly class ApiKeyHash
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new InvalidApiKeyHashError($value);
        }
    }
}
