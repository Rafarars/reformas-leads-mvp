<?php

namespace Reformas\Leads\Classification\Application\ApproveDraft;

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
        private Transaction $transaction,
        private EventBus $eventBus,
        private Clock $clock,
    ) {}

    public function __invoke(CustomerMessageDraftApproverRequest $request): void
    {
        $message = new CustomerMessageDraft($request->message);

        $this->transaction->run(function () use ($request, $message): void {
            $classification = $this->classifications->search(new LeadId($request->leadId))
                ?? throw new LeadClassificationNotFoundError($request->leadId);

            $classification->approveDraft($message, $request->approver, $this->clock->now());

            // Aprobación y aviso juntos: si el envío posterior falla, el outbox lo reintenta.
            $this->classifications->save($classification);
            $this->eventBus->publish(...$classification->pullDomainEvents());
        });
    }
}
