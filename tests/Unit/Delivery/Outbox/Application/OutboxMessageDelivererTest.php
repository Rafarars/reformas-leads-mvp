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

function pendingMessage(object $test, int $number = 1, string $destination = 'crm'): string
{
    $id = sprintf('00000000-0000-7000-8000-%012d', $number);
    $test->outbox->save(OutboxMessage::create(new OutboxMessageId($id), $destination, 'lead.received', '00000000-0000-7000-8000-0000000000aa', ['repeated' => false], $test->clock->now()));

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

// Un mensaje apartado hace días y reintentado a mano no puede apartarse de nuevo al primer fallo.
it('gives a replayed message a new retry window', function () {
    $id = pendingMessage($this);
    $this->clock->advance('+2 days');
    $message = $this->outbox->search(new OutboxMessageId($id));
    $message->markDead('HTTP 500');
    $message->replay($this->clock->now());
    $this->outbox->save($message);

    $outcome = outboxDeliverer($this, CallbackMessageDeliverer::alwaysFailing())(new OutboxMessageDelivererRequest($id));

    expect($outcome->type)->toBe(DeliveryOutcomeType::RetryScheduled)
        ->and(storedMessage($this, $id)['status'])->toBe('pending');
});

it('keeps delivering to the other destinations while one of them is down', function () {
    $crm = pendingMessage($this, 1, 'crm');
    $team = pendingMessage($this, 2, 'team');
    $deliver = outboxDeliverer($this, new CallbackMessageDeliverer(
        static fn (OutboxMessage $message): DeliveryResult => $message->destination() === 'crm' ? DeliveryResult::failed('HTTP 503') : DeliveryResult::succeeded(),
    ));

    // Cinco fallos del CRM abren solo su circuito.
    foreach (range(1, 5) as $attempt) {
        $this->outbox->save(OutboxMessage::fromPrimitives([...storedMessage($this, $crm), 'next_attempt_at' => $this->clock->now()->format(DATE_ATOM)]));
        $deliver(new OutboxMessageDelivererRequest($crm));
    }

    expect($deliver(new OutboxMessageDelivererRequest(pendingMessage($this, 3, 'crm')))->type)->toBe(DeliveryOutcomeType::CircuitOpen)
        ->and($deliver(new OutboxMessageDelivererRequest($team))->type)->toBe(DeliveryOutcomeType::Delivered);
});

it('isolates a permanent failure so the other recipient receives and the circuit stays closed', function () {
    $msgBroken = pendingMessage($this, 1, 'team');
    $msgHealthy = pendingMessage($this, 2, 'team');

    $deliverer = new CallbackMessageDeliverer(
        static fn (OutboxMessage $m): DeliveryResult => $m->id()->value === '00000000-0000-7000-8000-000000000001'
            ? DeliveryResult::failedPermanently('El destino respondió HTTP 422')
            : DeliveryResult::succeeded(),
    );

    $deliver = outboxDeliverer($this, $deliverer);

    $outcomeBroken = $deliver(new OutboxMessageDelivererRequest($msgBroken));
    $outcomeHealthy = $deliver(new OutboxMessageDelivererRequest($msgHealthy));

    expect($outcomeBroken->type)->toBe(DeliveryOutcomeType::GaveUp)
        ->and(storedMessage($this, $msgBroken)['status'])->toBe('dead')
        ->and(storedMessage($this, $msgBroken)['last_error'])->toBe('Fallo permanente: El destino respondió HTTP 422')
        ->and($outcomeHealthy->type)->toBe(DeliveryOutcomeType::Delivered)
        ->and(storedMessage($this, $msgHealthy)['status'])->toBe('delivered')
        // El circuito para 'team' NO se abre ni acumula fallos
        ->and($this->breakerStore->state('team')->consecutiveFailures)->toBe(0)
        ->and($deliver(new OutboxMessageDelivererRequest(pendingMessage($this, 3, 'team')))->type)->toBe(DeliveryOutcomeType::Delivered);
});

it('does not open the circuit across consecutive permanent failures in both directions', function () {
    $deliverer = new CallbackMessageDeliverer(static fn (): DeliveryResult => DeliveryResult::failedPermanently('HTTP 422'));
    $ids = array_map(fn (int $n): string => pendingMessage($this, $n, 'team'), range(1, 6));

    $outcomes = array_map(fn (string $id) => outboxDeliverer($this, $deliverer)(new OutboxMessageDelivererRequest($id))->type, $ids);

    // Cinco fallos permanentes van a la DLQ (GaveUp) y el 6º sigue intentándose (no salta CircuitOpen)
    expect($outcomes)->toBe(array_fill(0, 6, DeliveryOutcomeType::GaveUp))
        ->and($this->breakerStore->state('team')->consecutiveFailures)->toBe(0);
});
