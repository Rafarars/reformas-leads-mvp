<?php

namespace Reformas\Leads\Lead\Application\Receive;

use Reformas\Leads\Lead\Domain\Budget;
use Reformas\Leads\Lead\Domain\EmailAddress;
use Reformas\Leads\Lead\Domain\LeadDescription;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadName;
use Reformas\Leads\Lead\Domain\LeadRepository;
use Reformas\Leads\Lead\Domain\LeadSource;
use Reformas\Leads\Lead\Domain\LeadSubmission;
use Reformas\Leads\Lead\Domain\LeadZone;
use Reformas\Leads\Lead\Domain\PhoneNumber;
use Reformas\Leads\Lead\Domain\Register\LeadRegistrar;
use Reformas\Leads\Lead\Domain\RenovationType;
use Reformas\Shared\Domain\Clock;
use Reformas\Shared\Domain\EventBus;
use Reformas\Shared\Domain\Transaction;
use Reformas\Shared\Domain\UuidGenerator;

final readonly class LeadReceiver
{
    private LeadRegistrar $registrar;

    public function __construct(
        private LeadRepository $repository,
        private Transaction $transaction,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private EventBus $eventBus,
    ) {
        $this->registrar = new LeadRegistrar($repository);
    }

    public function __invoke(LeadReceiverRequest $request): LeadReceiverResponse
    {
        // Se valida antes de abrir la transacción: un dato inválido no llega a la base.
        $submission = new LeadSubmission(
            new LeadName($request->name),
            new PhoneNumber($request->phone),
            new EmailAddress($request->email),
            RenovationType::fromValue($request->renovationType),
            $request->zone === null ? null : new LeadZone($request->zone),
            $request->budget === null ? null : new Budget($request->budget),
            $request->description === null ? null : new LeadDescription($request->description),
            new LeadSource($request->source),
        );

        return $this->transaction->run(function () use ($submission): LeadReceiverResponse {
            $registration = ($this->registrar)(
                new LeadId($this->uuidGenerator->generate()),
                $submission,
                $this->clock->now(),
            );

            $this->repository->save($registration->lead);
            // En la misma transacción: o se guardan el lead y su aviso, o ninguno de los dos.
            $this->eventBus->publish(...$registration->lead->pullDomainEvents());

            $lead = $registration->lead->toPrimitives();

            return new LeadReceiverResponse($lead['id'], $lead['status'], $lead['contact_count'], $registration->repeated);
        });
    }
}
