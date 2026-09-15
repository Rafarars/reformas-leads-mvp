<?php

namespace Reformas\Leads\Classification\Domain;

// Lo que propone el modelo, ya validado: fuera de aquí no circula texto libre de la IA.
final readonly class ClassificationProposal
{
    public function __construct(
        public WorkCategory $category,
        public Urgency $urgency,
        public ClassificationConfidence $confidence,
        public ClassificationReason $reason,
        public ProjectSummary $summary,
        public LeadFindings $missingInformation,
        public LeadFindings $contradictions,
        public CustomerMessageDraft $customerMessage,
        public string $model,
    ) {}
}
