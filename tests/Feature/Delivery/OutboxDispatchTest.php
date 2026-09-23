<?php

use App\Models\User;
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
use Reformas\Leads\Classification\Domain\LeadClassifiedDomainEvent;
use Reformas\Leads\Classification\Domain\LeadContact;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenHash;
use Reformas\Notifications\TelegramLink\Domain\TelegramChatId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLink;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkRepository;
use Reformas\Shared\Domain\EventBus;

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

it('creates no outbox message for team when a lead is classified without any linked recipients', function () {
    Queue::fake();

    $event = new LeadClassifiedDomainEvent(
        '00000000-0000-7000-8000-000000000001',
        new DateTimeImmutable,
        ['renovation_type' => 'kitchen', 'confidence' => 0.95],
        new LeadContact('Ana García', '600111222', 'ana@example.com'),
    );

    app(EventBus::class)->publish($event);

    expect(DB::table('outbox_messages')->where('destination', 'team')->count())->toBe(0);
    Queue::assertNotPushed(DeliverOutboxMessageJob::class);
});

it('fans out one outbox message per linked telegram recipient when a lead is classified', function () {
    Queue::fake();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $linkRepo = app(TelegramLinkRepository::class);
    $now = new DateTimeImmutable;

    $link1 = TelegramLink::request(
        new TelegramLinkId('00000000-0000-7000-8000-000000000001'),
        $user1->id,
        new LinkTokenHash(hash('sha256', 'tok1')),
        $now->modify('+15 minutes'),
        $now,
    );
    $link1->complete(new TelegramChatId('111111'), $now);
    $linkRepo->save($link1);

    $link2 = TelegramLink::request(
        new TelegramLinkId('00000000-0000-7000-8000-000000000002'),
        $user2->id,
        new LinkTokenHash(hash('sha256', 'tok2')),
        $now->modify('+15 minutes'),
        $now,
    );
    $link2->complete(new TelegramChatId('222222'), $now);
    $linkRepo->save($link2);

    $event = new LeadClassifiedDomainEvent(
        '00000000-0000-7000-8000-0000000000aa',
        $now,
        ['renovation_type' => 'kitchen', 'confidence' => 0.95],
        new LeadContact('Ana García', '600111222', 'ana@example.com'),
    );

    app(EventBus::class)->publish($event);

    $messages = DB::table('outbox_messages')->where('destination', 'team')->get();
    expect($messages)->toHaveCount(2);

    $payloads = $messages->map(fn ($m) => json_decode((string) $m->payload, true))->all();
    $chatIds = array_column($payloads, 'chat_id');

    expect($chatIds)->toEqualCanonicalizing(['111111', '222222']);
    Queue::assertPushed(DeliverOutboxMessageJob::class, 2);
});
