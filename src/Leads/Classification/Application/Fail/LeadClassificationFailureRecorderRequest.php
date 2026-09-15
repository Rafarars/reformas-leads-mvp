<?php

namespace Reformas\Leads\Classification\Application\Fail;

final readonly class LeadClassificationFailureRecorderRequest
{
    public function __construct(public string $leadId) {}
}
