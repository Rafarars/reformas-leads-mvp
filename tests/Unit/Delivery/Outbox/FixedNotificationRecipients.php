<?php

namespace Tests\Unit\Delivery\Outbox;

use Reformas\Delivery\Outbox\Domain\NotificationRecipients;

final class FixedNotificationRecipients implements NotificationRecipients
{
    /**
     * @param  array<string, list<string>>  $chatIdsByDestination
     */
    public function __construct(private array $chatIdsByDestination = []) {}

    public function chatIdsFor(string $destination): array
    {
        return $this->chatIdsByDestination[$destination] ?? [];
    }
}
