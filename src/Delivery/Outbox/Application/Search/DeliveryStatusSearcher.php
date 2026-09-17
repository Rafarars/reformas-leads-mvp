<?php

namespace Reformas\Delivery\Outbox\Application\Search;

use DateTimeInterface;
use Reformas\Delivery\Outbox\Domain\CircuitBreaker;
use Reformas\Delivery\Outbox\Domain\CircuitBreakerStore;
use Reformas\Delivery\Outbox\Domain\DeliveryRouter;
use Reformas\Delivery\Outbox\Domain\OutboxMessage;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use Reformas\Shared\Domain\Clock;

/**
 * @phpstan-import-type MessageSummary from DeliveryStatusSearcherResponse
 * @phpstan-import-type CircuitSummary from DeliveryStatusSearcherResponse
 */
final readonly class DeliveryStatusSearcher
{
    private const LATEST_MESSAGES = 20;

    public function __construct(
        private OutboxRepository $outbox,
        private CircuitBreaker $circuitBreaker,
        private CircuitBreakerStore $circuitBreakerStore,
        private DeliveryRouter $router,
        private Clock $clock,
    ) {}

    public function __invoke(): DeliveryStatusSearcherResponse
    {
        return new DeliveryStatusSearcherResponse(
            $this->outbox->countByStatus(),
            array_map($this->circuit(...), $this->router->destinations()),
            array_map($this->summarize(...), $this->outbox->searchLatest(self::LATEST_MESSAGES)),
        );
    }

    /**
     * @return CircuitSummary
     */
    private function circuit(string $destination): array
    {
        $openUntil = $this->circuitBreaker->openUntil($destination, $this->clock->now());

        return [
            'destination' => $destination,
            'open' => $openUntil !== null,
            'open_until' => $openUntil?->format(DateTimeInterface::ATOM),
            'consecutive_failures' => $this->circuitBreakerStore->state($destination)->consecutiveFailures,
        ];
    }

    /**
     * @return MessageSummary
     */
    private function summarize(OutboxMessage $message): array
    {
        $primitives = $message->toPrimitives();
        // lead.received trae el lead en la raíz; los eventos de clasificación, en customer.
        $customer = $primitives['payload']['customer'] ?? null;
        $name = $primitives['payload']['name'] ?? (is_array($customer) ? ($customer['name'] ?? null) : null);

        return [
            'id' => $primitives['id'],
            'destination' => $primitives['destination'],
            'event' => $primitives['event_name'],
            'lead_name' => is_string($name) ? $name : null,
            'repeated' => ($primitives['payload']['repeated'] ?? false) === true,
            'status' => $primitives['status'],
            'attempts' => $primitives['attempts'],
            'next_attempt_at' => $primitives['next_attempt_at'],
            'last_error' => $primitives['last_error'],
            'created_at' => $primitives['created_at'],
            'delivered_at' => $primitives['delivered_at'],
        ];
    }
}
