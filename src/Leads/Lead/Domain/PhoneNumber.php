<?php

namespace Reformas\Leads\Lead\Domain;

// Formato internacional: "600 111 222" y "+34 600111222" tienen que ser la misma persona.
final readonly class PhoneNumber
{
    private const SPAIN_PREFIX = '+34';

    public string $value;

    public function __construct(string $value)
    {
        $this->value = self::normalize($value) ?? throw new InvalidPhoneNumberError($value);
    }

    private static function normalize(string $value): ?string
    {
        $compact = (string) preg_replace('/[\s().\-]/', '', $value);

        if (str_starts_with($compact, '00')) {
            $compact = '+'.substr($compact, 2);
        }

        // Nueve cifras sin prefijo que empiezan como un móvil o fijo español: la empresa opera en España.
        if (preg_match('/^[6-9]\d{8}$/', $compact) === 1) {
            $compact = self::SPAIN_PREFIX.$compact;
        }

        return preg_match('/^\+[1-9]\d{8,14}$/', $compact) === 1 ? $compact : null;
    }
}
