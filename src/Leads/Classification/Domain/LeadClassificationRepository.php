<?php

namespace Reformas\Leads\Classification\Domain;

use Reformas\Leads\Lead\Domain\LeadId;

interface LeadClassificationRepository
{
    public function save(LeadClassification $classification): void;

    public function search(LeadId $leadId): ?LeadClassification;

    // Primero las que esperan revisión; dentro de cada grupo, las más recientes.
    /**
     * @return list<LeadClassification>
     */
    public function searchLatest(int $limit): array;
}
