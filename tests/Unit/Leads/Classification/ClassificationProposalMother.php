<?php

namespace Tests\Unit\Leads\Classification;

use Reformas\Leads\Classification\Domain\ClassificationConfidence;
use Reformas\Leads\Classification\Domain\ClassificationProposal;
use Reformas\Leads\Classification\Domain\ClassificationReason;
use Reformas\Leads\Classification\Domain\CustomerMessageDraft;
use Reformas\Leads\Classification\Domain\LeadFindings;
use Reformas\Leads\Classification\Domain\ProjectSummary;
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
            new ProjectSummary('Cambiar el plato de ducha por una fuga que afecta al vecino.'),
            new LeadFindings(['Medidas del baño', 'Fotos del estado actual']),
            new LeadFindings([]),
            new CustomerMessageDraft("Hola {nombre},\n\nGracias por escribirnos. ¿Nos mandas fotos del baño?\n\nEl equipo de reformas"),
            'gemini-3.5-flash',
        );
    }
}
