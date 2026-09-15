<?php

namespace Reformas\Leads\Classification\Infrastructure\Queue;

use Reformas\Leads\Classification\Domain\ClassificationQueue;
use Reformas\Leads\Lead\Domain\LeadId;

final class LaravelClassificationQueue implements ClassificationQueue
{
    public function enqueue(LeadId $leadId): void
    {
        // Tras el commit: el trabajo nunca busca un lead que la transacción acabó revirtiendo.
        ClassifyLeadJob::dispatch($leadId->value)->afterCommit();
    }
}
