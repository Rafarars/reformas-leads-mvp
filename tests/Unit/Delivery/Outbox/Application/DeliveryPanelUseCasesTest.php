<?php

use Reformas\Delivery\Outbox\Application\Replay\OutboxMessageReplayer;
use Reformas\Delivery\Outbox\Application\Replay\OutboxMessageReplayerRequest;
use Reformas\Delivery\Outbox\Application\Search\DeliveryStatusSearcher;
use Reformas\Delivery\Outbox\Domain\CircuitBreaker;
use Reformas\Delivery\Outbox\Domain\OutboxMessage;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;
use Reformas\Delivery\Outbox\Domain\OutboxMessageNotDeadError;
use Reformas\Delivery\Outbox\Domain\OutboxMessageNotFoundError;
use Reformas\Delivery\Outbox\Infrastructure\InMemory\InMemoryCircuitBreakerStore;
use Reformas\Delivery\Outbox\Infrastructure\Persistence\InMemory\InMemoryOutboxRepository;
use Tests\Unit\Delivery\Outbox\SpyDeliveryQueue;
use Tests\Unit\Shared\FixedClock;

beforeEach(function () {
    $this->outbox = new InMemoryOutboxRepository;
    $this->clock = new FixedClock(new DateTimeImmutable('2026-09-15T10:00:00+00:00'));
    $this->breakerStore = new InMemoryCircuitBreakerStore;
    $this->breaker = new CircuitBreaker($this->breakerStore, failureThreshold: 2, cooldownSeconds: 30);
    $this->queue = new SpyDeliveryQueue;
});

function panelMessage(object $test, int $number, string $name = 'Ana García'): OutboxMessage
{
    $message = OutboxMessage::create(
        new OutboxMessageId(sprintf('00000000-0000-7000-8000-%012d', $number)),
        'lead.received',
        '00000000-0000-7000-8000-0000000000aa',
        ['name' => $name, 'repeated' => false],
        $test->clock->now()->modify('+'.$number.' seconds'),
    );
    $test->outbox->save($message);

    return $message;
}

it('summarizes the outbox and the circuit breaker for the panel', function () {
    panelMessage($this, 1, 'Ana García');
    $dead = panelMessage($this, 2, 'Luis Martínez');
    $dead->markDead('El destino respondió HTTP 500');
    $this->outbox->save($dead);
    $this->breaker->recordFailure($this->clock->now());
    $this->breaker->recordFailure($this->clock->now());

    $status = (new DeliveryStatusSearcher($this->outbox, $this->breaker, $this->breakerStore, $this->clock))()->toArray();

    expect($status['counts'])->toBe(['pending' => 1, 'delivered' => 0, 'dead' => 1])
        ->and($status['circuit'])->toBe(['open' => true, 'open_until' => '2026-09-15T10:00:30+00:00', 'consecutive_failures' => 2])
        ->and(array_column($status['messages'], 'lead_name'))->toBe(['Luis Martínez', 'Ana García'])
        ->and($status['messages'][0]['last_error'])->toBe('El destino respondió HTTP 500');
});

it('replays a message that was set aside and queues its delivery', function () {
    $message = panelMessage($this, 1);
    $message->markDead('HTTP 500');
    $this->outbox->save($message);

    (new OutboxMessageReplayer($this->outbox, $this->queue, $this->clock))(new OutboxMessageReplayerRequest($message->id()->value));

    expect($this->outbox->search($message->id())?->toPrimitives()['status'])->toBe('pending')
        ->and($this->queue->enqueued)->toBe([$message->id()->value]);
});

// Reintentar algo pendiente o entregado podría enviarlo dos veces.
it('refuses to replay a message that is not set aside', function () {
    $message = panelMessage($this, 1);

    (new OutboxMessageReplayer($this->outbox, $this->queue, $this->clock))(new OutboxMessageReplayerRequest($message->id()->value));
})->throws(OutboxMessageNotDeadError::class);

it('reports a message that does not exist', function () {
    (new OutboxMessageReplayer($this->outbox, $this->queue, $this->clock))(new OutboxMessageReplayerRequest('00000000-0000-7000-8000-000000000099'));
})->throws(OutboxMessageNotFoundError::class);
