<?php

namespace Reformas\Leads\Lead\Domain;

// Canal por el que entró el lead: "web-form", "facebook-ads". Sale del cliente de la API, no del cuerpo.
final readonly class LeadSource
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]{1,39}$/', $value) !== 1) {
            throw new InvalidLeadSourceError($value);
        }
    }
}
