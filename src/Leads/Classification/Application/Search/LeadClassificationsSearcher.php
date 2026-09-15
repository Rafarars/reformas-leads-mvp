<?php

namespace Reformas\Leads\Classification\Application\Search;

use Reformas\Leads\Classification\Domain\LeadClassificationRepository;
use Reformas\Leads\Lead\Domain\LeadRepository;

final readonly class LeadClassificationsSearcher
{
    private const LIMIT = 50;

    public function __construct(
        private LeadClassificationRepository $classifications,
        private LeadRepository $leads,
    ) {}

    public function __invoke(): LeadClassificationsSearcherResponse
    {
        $rows = [];

        foreach ($this->classifications->searchLatest(self::LIMIT) as $classification) {
            $lead = $this->leads->search($classification->leadId())?->toPrimitives();

            $rows[] = [
                ...$classification->toPrimitives(),
                // Lo que la persona escribió, al lado de lo que dedujo la IA: así se revisa.
                'lead' => $lead === null ? null : [
                    'name' => $lead['name'],
                    'renovation_type' => $lead['renovation_type'],
                    'zone' => $lead['zone'],
                    'budget' => $lead['budget'],
                    'description' => $lead['description'],
                    'received_at' => $lead['received_at'],
                ],
            ];
        }

        return new LeadClassificationsSearcherResponse($rows);
    }
}
