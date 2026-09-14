<?php

namespace Reformas\Leads\Lead\Domain;

final readonly class LeadDescription
{
    private const MAX_LENGTH = 5000;

    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);

        if ($normalized === '' || mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidLeadDescriptionError($value);
        }

        $this->value = $normalized;
    }
}
