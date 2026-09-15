<?php

namespace Reformas\Leads\Classification\Domain;

enum ClassificationStatus: string
{
    case Classified = 'classified';
    case NeedsReview = 'needs_review';
    case Reviewed = 'reviewed';
}
