<?php

namespace Reformas\Leads\Lead\Domain;

final readonly class Budget
{
    private const MAX_EUROS = 10_000_000;

    public function __construct(public int $euros)
    {
        if ($euros <= 0 || $euros > self::MAX_EUROS) {
            throw new InvalidBudgetError($euros);
        }
    }
}
