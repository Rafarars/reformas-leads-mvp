<?php

namespace Reformas\Leads\Classification\Application\Fail;

use Reformas\Leads\Classification\Application\LeadContactFinder;
use Reformas\Leads\Classification\Domain\LeadClassification;
use Reformas\Leads\Classification\Domain\LeadClassificationRepository;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Shared\Domain\Clock;
use Reformas\Shared\Domain\EventBus;
use Reformas\Shared\Domain\Transaction;

// Se agotaron los reintentos: el lead no se queda sin clasificar en silencio, pasa a una persona.
final readonly class LeadClassificationFailureRecorder
{
    public function __construct(
        private LeadClassificationRepository $classifications,
        private LeadContactFinder $findContact,
        private Transaction $transaction,
        private EventBus $eventBus,
        private Clock $clock,
    ) {}

    public function __invoke(LeadClassificationFailureRecorderRequest $request): void
    {
        $leadId = new LeadId($request->leadId);

        if ($this->classifications->search($leadId) !== null) {
            return;
        }

        $classification = LeadClassification::unclassified($leadId, 'La IA no respondió tras varios intentos.', ($this->findContact)($leadId), $this->clock->now());

        $this->transaction->run(function () use ($classification): void {
            $this->classifications->save($classification);
            $this->eventBus->publish(...$classification->pullDomainEvents());
        });
    }
}
