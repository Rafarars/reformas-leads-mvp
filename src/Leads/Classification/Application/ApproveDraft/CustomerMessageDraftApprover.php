<?php

namespace Reformas\Leads\Classification\Application\ApproveDraft;

use Reformas\Leads\Classification\Application\LeadContactFinder;
use Reformas\Leads\Classification\Domain\CustomerMessageDraft;
use Reformas\Leads\Classification\Domain\LeadClassificationNotFoundError;
use Reformas\Leads\Classification\Domain\LeadClassificationRepository;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Shared\Domain\Clock;
use Reformas\Shared\Domain\EventBus;
use Reformas\Shared\Domain\Transaction;

final readonly class CustomerMessageDraftApprover
{
    public function __construct(
        private LeadClassificationRepository $classifications,
        private LeadContactFinder $findContact,
        private Transaction $transaction,
        private EventBus $eventBus,
        private Clock $clock,
    ) {}

    public function __invoke(CustomerMessageDraftApproverRequest $request): void
    {
        $message = new CustomerMessageDraft($request->message);
        $leadId = new LeadId($request->leadId);

        $this->transaction->run(function () use ($request, $leadId, $message): void {
            $classification = $this->classifications->search($leadId)
                ?? throw new LeadClassificationNotFoundError($request->leadId);

            // El destinatario viaja en el evento: n8n envía el correo sin volver a consultar la base.
            $classification->approveDraft($message, $request->approver, ($this->findContact)($leadId), $this->clock->now());

            // Aprobación y aviso juntos: si el envío posterior falla, el outbox lo reintenta.
            $this->classifications->save($classification);
            $this->eventBus->publish(...$classification->pullDomainEvents());
        });
    }
}
