<?php

namespace Reformas\Leads\Lead\Domain;

// Lo que envía quien deja sus datos. Agruparlo evita pasar diez argumentos sueltos.
final readonly class LeadSubmission
{
    public function __construct(
        public LeadName $name,
        public PhoneNumber $phone,
        public EmailAddress $email,
        public RenovationType $renovationType,
        public ?LeadZone $zone,
        public ?Budget $budget,
        public ?LeadDescription $description,
        public LeadSource $source,
    ) {}
}
