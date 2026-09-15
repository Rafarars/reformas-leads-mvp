<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Reformas\Delivery\Outbox\Domain\OutboxMessage;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use Reformas\Delivery\Outbox\Infrastructure\Persistence\Eloquent\EloquentOutboxRepository;
use Reformas\Delivery\Outbox\Infrastructure\Persistence\InMemory\InMemoryOutboxRepository;

uses(RefreshDatabase::class);

dataset('outbox repositories', [
    'in memory' => fn () => new InMemoryOutboxRepository,
    'eloquent' => fn () => new EloquentOutboxRepository,
]);

function contractOutboxMessage(): OutboxMessage
{
    return OutboxMessage::create(
        new OutboxMessageId('00000000-0000-7000-8000-000000000001'),
        'lead.received',
        '00000000-0000-7000-8000-0000000000aa',
        ['name' => 'Ana García', 'budget' => 18_000, 'zone' => null, 'repeated' => false],
        new DateTimeImmutable('2026-09-15T10:00:00+00:00'),
    );
}

it('gives back a saved message exactly as it was, payload included', function (OutboxRepository $repository) {
    $message = contractOutboxMessage();
    $expected = $message->toPrimitives();

    $repository->save($message);

    expect($repository->search($message->id())?->toPrimitives())->toBe($expected);
})->with('outbox repositories');

it('returns null for a message that does not exist', function (OutboxRepository $repository) {
    expect($repository->search(new OutboxMessageId('00000000-0000-7000-8000-000000000099')))->toBeNull();
})->with('outbox repositories');

it('keeps the delivery state when saved again', function (OutboxRepository $repository) {
    $message = contractOutboxMessage();
    $repository->save($message);

    $message->recordFailedAttempt('Connection refused', new DateTimeImmutable('2026-09-15T10:00:02+00:00'));
    $message->markDelivered(new DateTimeImmutable('2026-09-15T10:00:05+00:00'));
    $repository->save($message);

    $stored = $repository->search($message->id())?->toPrimitives();

    expect($stored['status'])->toBe('delivered')
        ->and($stored['attempts'])->toBe(2)
        ->and($stored['last_error'])->toBeNull()
        ->and($stored['delivered_at'])->toBe('2026-09-15T10:00:05+00:00');
})->with('outbox repositories');

it('finds the pending messages whose retry is overdue, oldest first', function (OutboxRepository $repository) {
    $at = static fn (string $time): DateTimeImmutable => new DateTimeImmutable('2026-09-15T'.$time.'+00:00');
    $message = static fn (int $number, string $time): OutboxMessage => OutboxMessage::create(
        new OutboxMessageId(sprintf('00000000-0000-7000-8000-%012d', $number)), 'lead.received', '00000000-0000-7000-8000-0000000000aa', [], $at($time),
    );

    $late = $message(1, '10:05:00');
    $later = $message(2, '10:01:00');
    $notYet = $message(3, '10:09:30');
    $delivered = $message(4, '10:00:00');
    $delivered->markDelivered($at('10:00:01'));

    foreach ([$late, $later, $notYet, $delivered] as $each) {
        $repository->save($each);
    }

    $due = array_map(static fn (OutboxMessage $each): string => $each->id()->value, $repository->searchDue($at('10:09:00'), 10));

    expect($due)->toBe([$later->id()->value, $late->id()->value])
        ->and($repository->searchDue($at('10:09:00'), 1))->toHaveCount(1);
})->with('outbox repositories');

it('counts the messages by status', function (OutboxRepository $repository) {
    $at = new DateTimeImmutable('2026-09-15T10:00:00+00:00');
    $message = static fn (int $number): OutboxMessage => OutboxMessage::create(
        new OutboxMessageId(sprintf('00000000-0000-7000-8000-%012d', $number)), 'lead.received', '00000000-0000-7000-8000-0000000000aa', [], $at,
    );

    $pending = $message(1);
    $delivered = $message(2);
    $delivered->markDelivered($at);
    $dead = $message(3);
    $dead->markDead('HTTP 500');

    foreach ([$pending, $delivered, $dead, $message(4)] as $each) {
        $repository->save($each);
    }

    expect($repository->countByStatus())->toBe(['pending' => 2, 'delivered' => 1, 'dead' => 1]);
})->with('outbox repositories');

it('lists the latest messages first', function (OutboxRepository $repository) {
    foreach (['10:00:00', '10:02:00', '10:01:00'] as $number => $time) {
        $repository->save(OutboxMessage::create(
            new OutboxMessageId(sprintf('00000000-0000-7000-8000-%012d', $number + 1)), 'lead.received', '00000000-0000-7000-8000-0000000000aa', [],
            new DateTimeImmutable('2026-09-15T'.$time.'+00:00'),
        ));
    }

    $latest = array_map(static fn (OutboxMessage $each): string => $each->id()->value, $repository->searchLatest(2));

    expect($latest)->toBe(['00000000-0000-7000-8000-000000000002', '00000000-0000-7000-8000-000000000003']);
})->with('outbox repositories');

it('keeps the time of the last manual replay', function (OutboxRepository $repository) {
    $message = contractOutboxMessage();
    $message->markDead('HTTP 500');
    $message->replay(new DateTimeImmutable('2026-09-16T12:00:00+00:00'));
    $repository->save($message);

    expect($repository->search($message->id())?->toPrimitives()['last_replayed_at'])->toBe('2026-09-16T12:00:00+00:00');
})->with('outbox repositories');
