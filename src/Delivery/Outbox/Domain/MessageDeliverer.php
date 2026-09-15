<?php

namespace Reformas\Delivery\Outbox\Domain;

interface MessageDeliverer
{
    // Un fallo de red o una respuesta de error se devuelven, no se lanzan: son esperables.
    public function deliver(OutboxMessage $message): DeliveryResult;
}
