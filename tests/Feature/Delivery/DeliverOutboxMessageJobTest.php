<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Reformas\Delivery\Outbox\Domain\OutboxMessage;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use Reformas\Delivery\Outbox\Infrastructure\Queue\DeliverOutboxMessageJob;

uses(RefreshDatabase::class);

const JOB_MESSAGE_ID = '00000000-0000-7000-8000-000000000001';

beforeEach(function () {
    config(['services.delivery.destinations.crm.url' => 'https://crm.test/webhooks/leads']);

    app(OutboxRepository::class)->save(OutboxMessage::create(
        new OutboxMessageId(JOB_MESSAGE_ID), 'crm',
        'lead.received',
        '00000000-0000-7000-8000-0000000000aa',
        ['repeated' => false],
        new DateTimeImmutable,
    ));
});

function storedJobMessage(): array
{
    return app(OutboxRepository::class)->search(new OutboxMessageId(JOB_MESSAGE_ID))->toPrimitives();
}

function runDeliveryJob(): DeliverOutboxMessageJob
{
    $job = (new DeliverOutboxMessageJob(JOB_MESSAGE_ID))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

it('marks the message delivered when the destination answers', function () {
    Http::fake(['crm.test/*' => Http::response(null, 200)]);

    runDeliveryJob()->assertNotReleased();

    expect(storedJobMessage()['status'])->toBe('delivered');
});

it('puts the job back on the queue for the retry when the destination fails', function () {
    Http::fake(['crm.test/*' => Http::response(null, 503)]);

    runDeliveryJob()->assertReleased();

    expect(storedJobMessage()['status'])->toBe('pending')
        ->and(storedJobMessage()['attempts'])->toBe(1)
        ->and(storedJobMessage()['last_error'])->toBe('El destino respondió HTTP 503');
});

it('sets the message aside with the error when the job keeps crashing', function () {
    (new DeliverOutboxMessageJob(JOB_MESSAGE_ID))->failed(new RuntimeException('Fallo inesperado'));

    expect(storedJobMessage()['status'])->toBe('dead')
        ->and(storedJobMessage()['last_error'])->toContain('Fallo inesperado');
});
