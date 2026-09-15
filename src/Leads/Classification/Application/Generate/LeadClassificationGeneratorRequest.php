<?php

namespace Reformas\Leads\Classification\Application\Generate;

final readonly class LeadClassificationGeneratorRequest
{
    public function __construct(public string $leadId) {}
}
