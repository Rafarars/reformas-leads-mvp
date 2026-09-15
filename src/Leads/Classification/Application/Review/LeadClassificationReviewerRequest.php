<?php

namespace Reformas\Leads\Classification\Application\Review;

final readonly class LeadClassificationReviewerRequest
{
    public function __construct(
        public string $leadId,
        public string $category,
        public string $urgency,
        public string $reviewer,
    ) {}
}
