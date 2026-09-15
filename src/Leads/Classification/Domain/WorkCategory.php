<?php

namespace Reformas\Leads\Classification\Domain;

// Lo que la IA deduce del trabajo, que no siempre coincide con el tipo que marcó la persona.
enum WorkCategory: string
{
    case Kitchen = 'kitchen';
    case Bathroom = 'bathroom';
    case FullHome = 'full_home';
    case Facade = 'facade';
    case Installations = 'installations';
    case Other = 'other';

    public static function fromValue(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidWorkCategoryError($value);
    }
}
