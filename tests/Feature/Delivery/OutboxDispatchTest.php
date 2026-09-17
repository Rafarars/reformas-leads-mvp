<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Reformas\Delivery\Outbox\Domain\OutboxMessage;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use Reformas\Delivery\Outbox\Infrastructure\Queue\DeliverOutboxMessageJob;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreator;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreatorRequest;

uses(RefreshDatabase::class);

it('queues the delivery of the notice once the lead is received', function () {
    $apiKey = app(ApiClientCreator::class)(new ApiClientCreatorRequest('Formulario web', 'web-form'))->apiKey;

    $this->postJson('/api/v1/leads', [
        'name' => 'Ana García',
        'phone' => '600111222',
        'email' => 'ana@example.com',
        'renovation_type' => 'kitchen',
    ], ['Authorization' => 'Bearer '.$apiKey])->assertCreated();

    $messageId = DB::table('outbox_messages')->value('id');

    Queue::assertPushed(DeliverOutboxMessageJob::class, fn (DeliverOutboxMessageJob $job): bool => $job->messageId === $messageId);
});

it('re-queues only the pending messages whose retry is overdue', function () {
    $outbox = app(OutboxRepository::class);
    $message = static fn (int $number, string $createdAgo): OutboxMessage => OutboxMessage::create(
        new OutboxMessageId(sprintf('00000000-0000-7000-8000-%012d', $number)), 'crm',
        'lead.received',
        '00000000-0000-7000-8000-0000000000aa',
        [],
        (new DateTimeImmutable)->modify($createdAgo),
    );

    $stuck = $message(1, '-5 minutes');
    $scheduledNow = $message(2, '-10 seconds');
    $delivered = $message(3, '-5 minutes');
    $delivered->markDelivered(new DateTimeImmutable);

    foreach ([$stuck, $scheduledNow, $delivered] as $each) {
        $outbox->save($each);
    }

    Artisan::call('outbox:relay');

    Queue::assertPushed(DeliverOutboxMessageJob::class, 1);
    Queue::assertPushed(DeliverOutboxMessageJob::class, fn (DeliverOutboxMessageJob $job): bool => $job->messageId === $stuck->id()->value);
});
