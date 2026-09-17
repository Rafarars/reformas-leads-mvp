<?php

namespace Reformas\Leads\Classification\Application;

use Reformas\Leads\Classification\Domain\LeadContact;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadNotFoundError;
use Reformas\Leads\Lead\Domain\LeadRepository;

final readonly class LeadContactFinder
{
    public function __construct(private LeadRepository $leads) {}

    public function __invoke(LeadId $leadId): LeadContact
    {
        $lead = ($this->leads->search($leadId) ?? throw new LeadNotFoundError($leadId->value))->toPrimitives();

        return new LeadContact($lead['name'], $lead['email'], $lead['phone']);
    }
}
