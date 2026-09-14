<?php

namespace Reformas\Leads\Lead\Domain;

final readonly class EmailAddress
{
    private const MAX_LENGTH = 254;

    public string $value;

    public function __construct(string $value)
    {
        // En minúsculas: "Ana@Mail.com" y "ana@mail.com" son la misma persona para no duplicarla.
        $normalized = mb_strtolower(trim($value));

        if (mb_strlen($normalized) > self::MAX_LENGTH || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidEmailAddressError($value);
        }

        $this->value = $normalized;
    }
}
