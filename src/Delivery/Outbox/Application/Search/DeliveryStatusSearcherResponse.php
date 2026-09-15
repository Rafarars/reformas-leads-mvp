<?php

namespace Reformas\Delivery\Outbox\Application\Search;

/**
 * @phpstan-type MessageSummary array{
 *     id: string,
 *     event: string,
 *     lead_name: ?string,
 *     repeated: bool,
 *     status: string,
 *     attempts: int,
 *     next_attempt_at: ?string,
 *     last_error: ?string,
 *     created_at: string,
 *     delivered_at: ?string
 * }
 */
final readonly class DeliveryStatusSearcherResponse
{
    /**
     * @param  array{pending: int, delivered: int, dead: int}  $counts
     * @param  list<MessageSummary>  $messages
     */
    public function __construct(
        public array $counts,
        public ?string $circuitOpenUntil,
        public int $consecutiveFailures,
        public array $messages,
    ) {}

    /**
     * @return array{
     *     counts: array{pending: int, delivered: int, dead: int},
     *     circuit: array{open: bool, open_until: ?string, consecutive_failures: int},
     *     messages: list<MessageSummary>
     * }
     */
    public function toArray(): array
    {
        return [
            'counts' => $this->counts,
            'circuit' => [
                'open' => $this->circuitOpenUntil !== null,
                'open_until' => $this->circuitOpenUntil,
                'consecutive_failures' => $this->consecutiveFailures,
            ],
            'messages' => $this->messages,
        ];
    }
}
