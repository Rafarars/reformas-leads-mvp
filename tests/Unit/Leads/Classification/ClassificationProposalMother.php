<?php

namespace Tests\Unit\Leads\Classification;

use Reformas\Leads\Classification\Domain\ClassificationConfidence;
use Reformas\Leads\Classification\Domain\ClassificationProposal;
use Reformas\Leads\Classification\Domain\ClassificationReason;
use Reformas\Leads\Classification\Domain\Urgency;
use Reformas\Leads\Classification\Domain\WorkCategory;

final class ClassificationProposalMother
{
    public static function create(float $confidence = 0.9, WorkCategory $category = WorkCategory::Bathroom, Urgency $urgency = Urgency::High): ClassificationProposal
    {
        return new ClassificationProposal(
            $category,
            $urgency,
            new ClassificationConfidence($confidence),
            new ClassificationReason('Fuga activa bajo la ducha: baño y urgente.'),
            'gemini-3.5-flash',
        );
    }
}
