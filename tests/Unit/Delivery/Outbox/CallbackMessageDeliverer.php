<?php

namespace Tests\Unit\Delivery\Outbox;

use Closure;
use Reformas\Delivery\Outbox\Domain\DeliveryResult;
use Reformas\Delivery\Outbox\Domain\MessageDeliverer;
use Reformas\Delivery\Outbox\Domain\OutboxMessage;

final class CallbackMessageDeliverer implements MessageDeliverer
{
    /** @var list<string> */
    public array $attemptedIds = [];

    /**
     * @param  Closure(OutboxMessage): DeliveryResult  $respond
     */
    public function __construct(private readonly Closure $respond) {}

    public static function alwaysSucceeding(): self
    {
        return new self(static fn (): DeliveryResult => DeliveryResult::succeeded());
    }

    public static function alwaysFailing(string $reason = 'HTTP 503'): self
    {
        return new self(static fn (): DeliveryResult => DeliveryResult::failed($reason));
    }

    public function deliver(OutboxMessage $message): DeliveryResult
    {
        $this->attemptedIds[] = $message->id()->value;

        return ($this->respond)($message);
    }
}
