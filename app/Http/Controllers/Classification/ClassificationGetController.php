<?php

namespace App\Http\Controllers\Classification;

use Inertia\Inertia;
use Inertia\Response;
use Reformas\Leads\Classification\Application\Search\LeadClassificationsSearcher;
use Reformas\Leads\Classification\Domain\Urgency;
use Reformas\Leads\Classification\Domain\WorkCategory;

final class ClassificationGetController
{
    public function __invoke(LeadClassificationsSearcher $search): Response
    {
        return Inertia::render('classification/index', [
            ...$search()->toArray(),
            'categories' => array_column(WorkCategory::cases(), 'value'),
            'urgencies' => array_column(Urgency::cases(), 'value'),
        ]);
    }
}
