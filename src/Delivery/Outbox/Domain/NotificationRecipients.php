<?php

namespace Reformas\Delivery\Outbox\Domain;

// Destinatarios a los que entregar un aviso cuando el destino reparte por persona (abanico).
interface NotificationRecipients
{
    /**
     * @return list<string>
     */
    public function chatIdsFor(string $destination): array;
}
