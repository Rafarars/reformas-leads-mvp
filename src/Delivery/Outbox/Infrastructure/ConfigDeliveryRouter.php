<?php

namespace Reformas\Delivery\Outbox\Infrastructure;

use Reformas\Delivery\Outbox\Domain\DeliveryRouter;

final readonly class ConfigDeliveryRouter implements DeliveryRouter
{
    /**
     * @param  array<string, list<string>>  $eventsByDestination
     */
    public function __construct(private array $eventsByDestination) {}

    public function destinationsFor(string $eventName): array
    {
        $destinations = [];

        foreach ($this->eventsByDestination as $destination => $events) {
            if (in_array($eventName, $events, true)) {
                $destinations[] = $destination;
            }
        }

        return $destinations;
    }

    public function destinations(): array
    {
        return array_keys($this->eventsByDestination);
    }
}
