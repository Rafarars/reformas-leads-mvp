<?php

namespace Reformas\Leads\Classification\Domain;

final readonly class ProjectSummary
{
    private const MAX_LENGTH = 300;

    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($normalized === '' || mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidProjectSummaryError($value);
        }

        $this->value = $normalized;
    }
}
