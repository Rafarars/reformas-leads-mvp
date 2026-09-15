<?php

namespace Reformas\Leads\Classification\Domain;

enum Urgency: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public static function fromValue(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidUrgencyError($value);
    }
}
