<?php

namespace Reformas\Leads\Classification\Domain;

final readonly class ClassificationReason
{
    private const MAX_LENGTH = 500;

    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($normalized === '' || mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidClassificationReasonError($value);
        }

        $this->value = $normalized;
    }
}
