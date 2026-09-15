<?php

namespace Reformas\Leads\Classification\Domain;

use Reformas\Leads\Lead\Domain\LeadId;

interface ClassificationQueue
{
    public function enqueue(LeadId $leadId): void;
}
