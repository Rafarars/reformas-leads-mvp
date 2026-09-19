<?php

namespace App\Http\Controllers\Classification;

use Inertia\Inertia;
use Inertia\Response;
use Reformas\Leads\Attachment\Application\Search\LeadAttachmentsSearcher;
use Reformas\Leads\Classification\Application\Search\LeadClassificationsSearcher;
use Reformas\Leads\Classification\Domain\Urgency;
use Reformas\Leads\Classification\Domain\WorkCategory;

final class ClassificationGetController
{
    public function __invoke(LeadClassificationsSearcher $search, LeadAttachmentsSearcher $searchAttachments): Response
    {
        $classifications = array_map(
            // Las fotos y los planos se revisan junto a la clasificación: son lo que falta para presupuestar.
            static fn (array $classification): array => [
                ...$classification,
                'attachments' => $searchAttachments(is_string($classification['lead_id']) ? $classification['lead_id'] : ''),
            ],
            $search()->classifications,
        );

        return Inertia::render('classification/index', [
            'classifications' => $classifications,
            'categories' => array_column(WorkCategory::cases(), 'value'),
            'urgencies' => array_column(Urgency::cases(), 'value'),
        ]);
    }
}
