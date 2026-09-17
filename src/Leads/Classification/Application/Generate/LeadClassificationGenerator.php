<?php

namespace Reformas\Leads\Classification\Application\Generate;

use Reformas\Leads\Classification\Domain\ClassifierNotConfiguredException;
use Reformas\Leads\Classification\Domain\ClassifierUnavailableException;
use Reformas\Leads\Classification\Domain\InvalidClassifierOutputException;
use Reformas\Leads\Classification\Domain\LeadClassification;
use Reformas\Leads\Classification\Domain\LeadClassificationRepository;
use Reformas\Leads\Classification\Domain\LeadClassifier;
use Reformas\Leads\Classification\Domain\LeadContact;
use Reformas\Leads\Classification\Domain\LeadToClassify;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadNotFoundError;
use Reformas\Leads\Lead\Domain\LeadRepository;
use Reformas\Shared\Domain\Clock;
use Reformas\Shared\Domain\EventBus;
use Reformas\Shared\Domain\Transaction;

final readonly class LeadClassificationGenerator
{
    public function __construct(
        private LeadRepository $leads,
        private LeadClassificationRepository $classifications,
        private LeadClassifier $classifier,
        private Transaction $transaction,
        private EventBus $eventBus,
        private Clock $clock,
        private float $minConfidence,
    ) {}

    /**
     * @throws ClassifierUnavailableException la cola lo reintenta más tarde
     */
    public function __invoke(LeadClassificationGeneratorRequest $request): void
    {
        $leadId = new LeadId($request->leadId);

        // Idempotente: un reintento de la cola nunca pisa una clasificación ya hecha, ni una revisada a mano.
        if ($this->classifications->search($leadId) !== null) {
            return;
        }

        $lead = ($this->leads->search($leadId) ?? throw new LeadNotFoundError($request->leadId))->toPrimitives();
        $customer = new LeadContact($lead['name'], $lead['email'], $lead['phone']);

        $toClassify = new LeadToClassify($lead['renovation_type'], $lead['zone'], $lead['budget'], $lead['description']);

        // La llamada al modelo va fuera de la transacción: no se retiene la base mientras responde.
        try {
            $classification = LeadClassification::fromProposal($leadId, $this->classifier->classify($toClassify), $this->minConfidence, $customer, $this->clock->now());
        } catch (InvalidClassifierOutputException) {
            $classification = LeadClassification::unclassified($leadId, 'La IA respondió algo que no se pudo validar.', $customer, $this->clock->now());
        } catch (ClassifierNotConfiguredException) {
            $classification = LeadClassification::unclassified($leadId, 'La clasificación automática no está configurada o el modelo rechazó la petición.', $customer, $this->clock->now());
        }

        $this->transaction->run(function () use ($classification): void {
            $this->classifications->save($classification);
            $this->eventBus->publish(...$classification->pullDomainEvents());
        });
    }
}
