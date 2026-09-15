<?php

namespace Reformas\Leads\Classification\Application\Generate;

use Reformas\Leads\Classification\Domain\ClassifierNotConfiguredException;
use Reformas\Leads\Classification\Domain\ClassifierUnavailableException;
use Reformas\Leads\Classification\Domain\InvalidClassifierOutputException;
use Reformas\Leads\Classification\Domain\LeadClassification;
use Reformas\Leads\Classification\Domain\LeadClassificationRepository;
use Reformas\Leads\Classification\Domain\LeadClassifier;
use Reformas\Leads\Classification\Domain\LeadToClassify;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadNotFoundError;
use Reformas\Leads\Lead\Domain\LeadRepository;
use Reformas\Shared\Domain\Clock;

final readonly class LeadClassificationGenerator
{
    public function __construct(
        private LeadRepository $leads,
        private LeadClassificationRepository $classifications,
        private LeadClassifier $classifier,
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

        $toClassify = new LeadToClassify($lead['renovation_type'], $lead['zone'], $lead['budget'], $lead['description']);

        try {
            $classification = LeadClassification::fromProposal($leadId, $this->classifier->classify($toClassify), $this->minConfidence, $this->clock->now());
        } catch (InvalidClassifierOutputException) {
            $classification = LeadClassification::unclassified($leadId, 'La IA respondió algo que no se pudo validar.', $this->clock->now());
        } catch (ClassifierNotConfiguredException) {
            $classification = LeadClassification::unclassified($leadId, 'La clasificación automática no está configurada o el modelo rechazó la petición.', $this->clock->now());
        }

        $this->classifications->save($classification);
    }
}
