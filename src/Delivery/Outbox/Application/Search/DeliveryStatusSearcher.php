<?php

namespace Reformas\Delivery\Outbox\Application\Search;

use DateTimeInterface;
use Reformas\Delivery\Outbox\Domain\CircuitBreaker;
use Reformas\Delivery\Outbox\Domain\CircuitBreakerStore;
use Reformas\Delivery\Outbox\Domain\OutboxMessage;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use Reformas\Shared\Domain\Clock;

/**
 * @phpstan-import-type MessageSummary from DeliveryStatusSearcherResponse
 */
final readonly class DeliveryStatusSearcher
{
    private const LATEST_MESSAGES = 20;

    public function __construct(
        private OutboxRepository $outbox,
        private CircuitBreaker $circuitBreaker,
        private CircuitBreakerStore $circuitBreakerStore,
        private Clock $clock,
    ) {}

    public function __invoke(): DeliveryStatusSearcherResponse
    {
        return new DeliveryStatusSearcherResponse(
            $this->outbox->countByStatus(),
            $this->circuitBreaker->openUntil($this->clock->now())?->format(DateTimeInterface::ATOM),
            $this->circuitBreakerStore->state()->consecutiveFailures,
            array_map($this->summarize(...), $this->outbox->searchLatest(self::LATEST_MESSAGES)),
        );
    }

    /**
     * @return MessageSummary
     */
    private function summarize(OutboxMessage $message): array
    {
        $primitives = $message->toPrimitives();
        $name = $primitives['payload']['name'] ?? null;

        return [
            'id' => $primitives['id'],
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
