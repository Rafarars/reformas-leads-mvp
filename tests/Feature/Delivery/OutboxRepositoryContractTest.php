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
