<?php

namespace Reformas\Leads\Classification\Domain;

final readonly class CustomerMessageDraft
{
    // El modelo no conoce el nombre de la persona: escribe este marcador y se sustituye aquí.
    public const NAME_PLACEHOLDER = '{nombre}';

    private const MAX_LENGTH = 1500;

    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);

        if ($normalized === '' || mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidCustomerMessageDraftError($value);
        }

        $this->value = $normalized;
    }
}
