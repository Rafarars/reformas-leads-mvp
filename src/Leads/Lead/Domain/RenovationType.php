<?php

namespace Reformas\Leads\Lead\Domain;

enum RenovationType: string
{
    case Kitchen = 'kitchen';
    case Bathroom = 'bathroom';
    case FullHome = 'full_home';
    case Partial = 'partial';
    case Other = 'other';

    public static function fromValue(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidRenovationTypeError($value);
    }
}
