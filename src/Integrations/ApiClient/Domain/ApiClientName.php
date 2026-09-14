<?php

namespace Reformas\Integrations\ApiClient\Domain;

final readonly class ApiClientName
{
    private const MIN_LENGTH = 2;

    private const MAX_LENGTH = 80;

    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);
        $length = mb_strlen($normalized);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new InvalidApiClientNameError($value);
        }

        $this->value = $normalized;
    }
}
