<?php

namespace Reformas\Delivery\Outbox\Domain;

// Qué destinos reciben cada evento. Un mensaje del outbox por destino: cada uno se reintenta por separado.
interface DeliveryRouter
{
    /**
     * @return list<string>
     */
    public function destinationsFor(string $eventName): array;

    /**
     * @return list<string>
     */
    public function destinations(): array;
}
