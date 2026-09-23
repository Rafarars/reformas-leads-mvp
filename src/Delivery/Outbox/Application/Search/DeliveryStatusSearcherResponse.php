<?php

namespace Reformas\Delivery\Outbox\Application\Search;

/**
 * @phpstan-type CircuitSummary array{destination: string, open: bool, open_until: ?string, consecutive_failures: int}
 * @phpstan-type MessageSummary array{
 *     id: string,
 *     destination: string,
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
     * @param  list<CircuitSummary>  $circuits
     * @param  list<MessageSummary>  $messages
     * @param  array<string, int>  $recipients
     */
    public function __construct(
        public array $counts,
        public array $circuits,
        public array $messages,
        public array $recipients = [],
    ) {}

    /**
     * @return array{
     *     counts: array{pending: int, delivered: int, dead: int},
     *     circuits: list<CircuitSummary>,
     *     messages: list<MessageSummary>,
     *     recipients: array<string, int>
     * }
     */
    public function toArray(): array
    {
        return [
            'counts' => $this->counts,
            'circuits' => $this->circuits,
            'messages' => $this->messages,
            'recipients' => $this->recipients,
        ];
    }
}
