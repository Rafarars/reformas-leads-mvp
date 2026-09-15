<?php

namespace Reformas\Leads\Classification\Application\Review;

use Reformas\Leads\Classification\Domain\LeadClassificationNotFoundError;
use Reformas\Leads\Classification\Domain\LeadClassificationRepository;
use Reformas\Leads\Classification\Domain\Urgency;
use Reformas\Leads\Classification\Domain\WorkCategory;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Shared\Domain\Clock;

final readonly class LeadClassificationReviewer
{
    public function __construct(
        private LeadClassificationRepository $classifications,
        private Clock $clock,
    ) {}

    public function __invoke(LeadClassificationReviewerRequest $request): void
    {
        $category = WorkCategory::fromValue($request->category);
        $urgency = Urgency::fromValue($request->urgency);

        $classification = $this->classifications->search(new LeadId($request->leadId))
            ?? throw new LeadClassificationNotFoundError($request->leadId);

        $classification->review($category, $urgency, $request->reviewer, $this->clock->now());
        $this->classifications->save($classification);
    }
}
