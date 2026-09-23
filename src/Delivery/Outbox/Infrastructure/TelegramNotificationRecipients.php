<?php

namespace Reformas\Delivery\Outbox\Infrastructure;

use Reformas\Delivery\Outbox\Domain\NotificationRecipients;
use Reformas\Notifications\TelegramLink\Application\List\ListRecipients;

// Adaptador que resuelve los destinatarios de Telegram apoyándose en el contexto Notifications.
// El dominio de Delivery no conoce Notifications: la dependencia vive solo aquí.
final readonly class TelegramNotificationRecipients implements NotificationRecipients
{
    public function __construct(private ListRecipients $listRecipients) {}

    /**
     * @return list<string>
     */
    public function chatIdsFor(string $destination): array
    {
        return ($this->listRecipients)()->chatIds();
    }
}
