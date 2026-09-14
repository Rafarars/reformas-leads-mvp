<?php

namespace Reformas\Shared\Domain;

abstract readonly class Uuid
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public string $value;

    final public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (preg_match(self::PATTERN, $normalized) !== 1) {
            throw new InvalidUuidError($value);
        }

        $this->value = $normalized;
    }

    public function equals(self $other): bool
    {
        return $other instanceof static && $this->value === $other->value;
    }
}
