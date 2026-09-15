<?php

namespace Reformas\Leads\Classification\Domain;

interface LeadClassifier
{
    /**
     * @throws ClassifierUnavailableException caída pasajera: se reintenta
     * @throws ClassifierNotConfiguredException sin key o petición rechazada: reintentar no sirve
     * @throws InvalidClassifierOutputException el modelo respondió algo que no cumple el contrato
     */
    public function classify(LeadToClassify $lead): ClassificationProposal;
}
