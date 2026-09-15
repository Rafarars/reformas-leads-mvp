<?php

use Reformas\Delivery\Outbox\Application\Deliver\DeliveryOutcomeType;
use Reformas\Delivery\Outbox\Application\Deliver\OutboxMessageDeliverer;
use Reformas\Delivery\Outbox\Application\Deliver\OutboxMessageDelivererRequest;
use Reformas\Delivery\Outbox\Domain\CircuitBreaker;
use Reformas\Delivery\Outbox\Domain\DeliveryResult;
use Reformas\Delivery\Outbox\Domain\MessageDeliverer;
use Reformas\Delivery\Outbox\Domain\OutboxMessage;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;
use Reformas\Delivery\Outbox\Domain\RetryPolicy;
use Reformas\Delivery\Outbox\Infrastructure\InMemory\InMemoryCircuitBreakerStore;
use Reformas\Delivery\Outbox\Infrastructure\Persistence\InMemory\InMemoryOutboxRepository;
use Tests\Unit\Delivery\Outbox\CallbackMessageDeliverer;
use Tests\Unit\Shared\FixedClock;
use Tests\Unit\Shared\FixedRandom;

beforeEach(function () {
    $this->outbox = new InMemoryOutboxRepository;
    $this->clock = new FixedClock(new DateTimeImmutable('2026-09-15T10:00:00+00:00'));
    $this->breakerStore = new InMemoryCircuitBreakerStore;
});

function outboxDeliverer(object $test, MessageDeliverer $deliverer): OutboxMessageDeliverer
{
    return new OutboxMessageDeliverer(
        $test->outbox,
        $deliverer,
        new CircuitBreaker($test->breakerStore, failureThreshold: 5, cooldownSeconds: 30),
        new RetryPolicy(new FixedRandom(alwaysMax: true), baseDelaySeconds: 2, maxDelaySeconds: 300, giveUpAfterSeconds: 86_400),
        $test->clock,
    );
}

function pendingMessage(object $test, int $number = 1): string
{
    $id = sprintf('00000000-0000-7000-8000-%012d', $number);
    $test->outbox->save(OutboxMessage::create(new OutboxMessageId($id), 'lead.received', '00000000-0000-7000-8000-0000000000aa', ['repeated' => false], $test->clock->now()));

    return $id;
}

function storedMessage(object $test, string $id): array
{
    return $test->outbox->search(new OutboxMessageId($id))->toPrimitives();
}

it('delivers a pending message and marks it as delivered', function () {
    $id = pendingMessage($this);

    $outcome = outboxDeliverer($this, CallbackMessageDeliverer::alwaysSucceeding())(new OutboxMessageDelivererRequest($id));

    expect($outcome->type)->toBe(DeliveryOutcomeType::Delivered)
        ->and(storedMessage($this, $id)['status'])->toBe('delivered')
        ->and(storedMessage($this, $id)['attempts'])->toBe(1);
});

it('schedules a retry with backoff when the delivery fails', function () {
    $id = pendingMessage($this);

    $outcome = outboxDeliverer($this, CallbackMessageDeliverer::alwaysFailing('HTTP 503'))(new OutboxMessageDelivererRequest($id));
    $stored = storedMessage($this, $id);

    expect($outcome->type)->toBe(DeliveryOutcomeType::RetryScheduled)
        ->and($outcome->retryAt?->format(DATE_ATOM))->toBe('2026-09-15T10:00:02+00:00')
        ->and($stored['status'])->toBe('pending')
        ->and($stored['attempts'])->toBe(1)
        ->and($stored['last_error'])->toBe('HTTP 503');
});

it('does not send a message that was already delivered', function () {
    $id = pendingMessage($this);
    $deliverer = CallbackMessageDeliverer::alwaysSucceeding();
    outboxDeliverer($this, $deliverer)(new OutboxMessageDelivererRequest($id));

    $outcome = outboxDeliverer($this, $deliverer)(new OutboxMessageDelivererRequest($id));

    expect($outcome->type)->toBe(DeliveryOutcomeType::Skipped)
        ->and($deliverer->attemptedIds)->toHaveCount(1);
});

it('stops calling the service after consecutive failures, without spending attempts', function () {
    $deliverer = CallbackMessageDeliverer::alwaysFailing();
    $ids = array_map(fn (int $number): string => pendingMessage($this, $number), range(1, 6));

    $outcomes = array_map(fn (string $id) => outboxDeliverer($this, $deliverer)(new OutboxMessageDelivererRequest($id))->type, $ids);

    expect($outcomes)->toBe([...array_fill(0, 5, DeliveryOutcomeType::RetryScheduled), DeliveryOutcomeType::CircuitOpen])
        ->and($deliverer->attemptedIds)->toHaveCount(5)
        ->and(storedMessage($this, $ids[5])['attempts'])->toBe(0);
});

it('sets a message aside with its last error once it is too old to keep retrying', function () {
    $id = pendingMessage($this);
    $this->clock->advance('+1 day');

    $outcome = outboxDeliverer($this, CallbackMessageDeliverer::alwaysFailing('HTTP 500'))(new OutboxMessageDelivererRequest($id));
    $stored = storedMessage($this, $id);

    expect($outcome->type)->toBe(DeliveryOutcomeType::GaveUp)
        ->and($stored['status'])->toBe('dead')
        ->and($stored['last_error'])->toBe('HTTP 500');
});

// La pregunta de la entrevista: el servicio externo cae 30 minutos. El trabajo vuelve cuando le
// dicen (reintento o circuito abierto) hasta que el servicio responde.
it('survives a 30-minute outage and delivers the message once the service is back', function () {
    $id = pendingMessage($this);
    $backAt = $this->clock->now()->modify('+30 minutes');
    $deliverer = new CallbackMessageDeliverer(fn (): DeliveryResult => $this->clock->now() < $backAt
        ? DeliveryResult::failed('Connection refused')
        : DeliveryResult::succeeded());
    $deliver = outboxDeliverer($this, $deliverer);

    $outcomes = [];
    do {
        $outcome = $deliver(new OutboxMessageDelivererRequest($id));
        $outcomes[] = $outcome->type;

        if ($outcome->retryAt !== null) {
            $this->clock->advance('+'.($outcome->retryAt->getTimestamp() - $this->clock->now()->getTimestamp()).' seconds');
        }
    } while ($outcome->type !== DeliveryOutcomeType::Delivered && count($outcomes) < 500);

    expect($outcome->type)->toBe(DeliveryOutcomeType::Delivered)
        ->and($outcomes)->not->toContain(DeliveryOutcomeType::GaveUp)
        ->and(storedMessage($this, $id)['status'])->toBe('delivered')
        // Sin backoff ni circuito serían cientos de llamadas contra un servicio caído.
        ->and(count($deliverer->attemptedIds))->toBeLessThan(20)
        ->and(array_unique($deliverer->attemptedIds))->toBe([$id]);
});

it('does not attempt again before the scheduled retry time', function () {
    $id = pendingMessage($this);
    $deliverer = CallbackMessageDeliverer::alwaysFailing();
    outboxDeliverer($this, $deliverer)(new OutboxMessageDelivererRequest($id));

    $outcome = outboxDeliverer($this, $deliverer)(new OutboxMessageDelivererRequest($id));

    expect($outcome->type)->toBe(DeliveryOutcomeType::RetryScheduled)
        ->and($outcome->retryAt?->format(DATE_ATOM))->toBe('2026-09-15T10:00:02+00:00')
        ->and($deliverer->attemptedIds)->toHaveCount(1)
        ->and(storedMessage($this, $id)['attempts'])->toBe(1);
});
