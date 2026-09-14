<?php

namespace Reformas\Leads\Lead\Domain;

final readonly class LeadName
{
    private const MIN_LENGTH = 2;

    private const MAX_LENGTH = 120;

    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $value));
        $length = mb_strlen($normalized);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new InvalidLeadNameError($value);
        }

        $this->value = $normalized;
    }
}
