<?php

namespace Reformas\Leads\Classification\Application\Fail;

use Reformas\Leads\Classification\Domain\LeadClassification;
use Reformas\Leads\Classification\Domain\LeadClassificationRepository;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Shared\Domain\Clock;

// Se agotaron los reintentos: el lead no se queda sin clasificar en silencio, pasa a una persona.
final readonly class LeadClassificationFailureRecorder
{
    public function __construct(
        private LeadClassificationRepository $classifications,
        private Clock $clock,
    ) {}

    public function __invoke(LeadClassificationFailureRecorderRequest $request): void
    {
        $leadId = new LeadId($request->leadId);

        if ($this->classifications->search($leadId) !== null) {
            return;
        }

        $this->classifications->save(LeadClassification::unclassified($leadId, 'La IA no respondió tras varios intentos.', $this->clock->now()));
    }
}
