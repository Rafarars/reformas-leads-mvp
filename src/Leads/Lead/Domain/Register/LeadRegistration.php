<?php

namespace Reformas\Leads\Lead\Domain\Register;

use Reformas\Leads\Lead\Domain\Lead;

final readonly class LeadRegistration
{
    public function __construct(
        public Lead $lead,
        public bool $repeated,
    ) {}
}
