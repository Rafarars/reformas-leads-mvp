<?php

use Reformas\Delivery\Outbox\Infrastructure\ConfigDeliveryRouter;
use Reformas\Delivery\Outbox\Infrastructure\OutboxEventBus;
use Reformas\Delivery\Outbox\Infrastructure\Persistence\InMemory\InMemoryOutboxRepository;
use Reformas\Shared\Domain\DomainEvent;
use Tests\Unit\Delivery\Outbox\FixedNotificationRecipients;
use Tests\Unit\Delivery\Outbox\SpyDeliveryQueue;
use Tests\Unit\Shared\FixedClock;
use Tests\Unit\Shared\SequentialUuidGenerator;

final readonly class RoutedTestEvent extends DomainEvent
{
    public function __construct(private string $name)
    {
        parent::__construct('00000000-0000-7000-8000-0000000000aa', new DateTimeImmutable);
    }

    public static function eventName(): string
    {
        return 'test.event';
    }

    public function body(): array
    {
        return ['name' => $this->name];
    }
}

function routedBus(
    array $eventsByDestination,
    InMemoryOutboxRepository $outbox,
    SpyDeliveryQueue $queue,
    array $fanOutDestinations = [],
    array $chatIdsByDestination = [],
): OutboxEventBus {
    return new OutboxEventBus(
        $outbox,
        new SequentialUuidGenerator,
        new FixedClock,
        $queue,
        new ConfigDeliveryRouter($eventsByDestination, $fanOutDestinations),
        new FixedNotificationRecipients($chatIdsByDestination),
    );
}

it('routes each event only to the destinations subscribed to it', function () {
    $router = new ConfigDeliveryRouter(
        ['crm' => ['lead.received'], 'team' => ['lead.classified'], 'customer' => ['lead.customer_message_approved']],
        ['team'],
    );

    expect($router->destinationsFor('lead.received'))->toBe(['crm'])
        ->and($router->destinationsFor('lead.classified'))->toBe(['team'])
        ->and($router->destinationsFor('unknown'))->toBe([])
        ->and($router->destinations())->toBe(['crm', 'team', 'customer'])
        ->and($router->isFanOut('team'))->toBeTrue()
        ->and($router->isFanOut('crm'))->toBeFalse();
});

it('stores one message per destination, so each one retries on its own', function () {
    $outbox = new InMemoryOutboxRepository;
    $queue = new SpyDeliveryQueue;

    routedBus(['crm' => ['test.event'], 'team' => ['test.event'], 'customer' => ['other.event']], $outbox, $queue)->publish(new RoutedTestEvent('Ana'));

    $messages = array_map(static fn ($message): array => $message->toPrimitives(), $outbox->searchLatest(10));

    expect(array_column($messages, 'destination'))->toEqualCanonicalizing(['crm', 'team'])
        ->and(array_unique(array_column($messages, 'event_name')))->toBe(['test.event'])
        ->and($queue->enqueued)->toHaveCount(2);
});

it('fans out one message per recipient for fan-out destinations, each with its chat_id in payload', function () {
    $outbox = new InMemoryOutboxRepository;
    $queue = new SpyDeliveryQueue;

    routedBus(
        eventsByDestination: ['team' => ['test.event'], 'crm' => ['test.event']],
        outbox: $outbox,
        queue: $queue,
        fanOutDestinations: ['team'],
        chatIdsByDestination: ['team' => ['111111', '222222']],
    )->publish(new RoutedTestEvent('Ana'));

    $messages = array_map(static fn ($message): array => $message->toPrimitives(), $outbox->searchLatest(10));

    $teamMessages = array_values(array_filter($messages, static fn (array $m): bool => $m['destination'] === 'team'));
    $crmMessages = array_values(array_filter($messages, static fn (array $m): bool => $m['destination'] === 'crm'));

    expect($teamMessages)->toHaveCount(2)
        ->and($crmMessages)->toHaveCount(1)
        ->and($teamMessages[0]['payload'])->toBe(['name' => 'Ana', 'chat_id' => '222222'])
        ->and($teamMessages[1]['payload'])->toBe(['name' => 'Ana', 'chat_id' => '111111'])
        ->and($crmMessages[0]['payload'])->toBe(['name' => 'Ana'])
        ->and($queue->enqueued)->toHaveCount(3);
});

it('stores zero messages for a fan-out destination when there are no recipients', function () {
    $outbox = new InMemoryOutboxRepository;
    $queue = new SpyDeliveryQueue;

    routedBus(
        eventsByDestination: ['team' => ['test.event'], 'crm' => ['test.event']],
        outbox: $outbox,
        queue: $queue,
        fanOutDestinations: ['team'],
        chatIdsByDestination: ['team' => []],
    )->publish(new RoutedTestEvent('Ana'));

    $messages = array_map(static fn ($message): array => $message->toPrimitives(), $outbox->searchLatest(10));

    expect(array_column($messages, 'destination'))->toBe(['crm'])
        ->and($queue->enqueued)->toHaveCount(1);
});

it('stores nothing for an event no destination listens to', function () {
    $outbox = new InMemoryOutboxRepository;
    $queue = new SpyDeliveryQueue;

    routedBus(['crm' => ['lead.received']], $outbox, $queue)->publish(new RoutedTestEvent('Ana'));

    expect($outbox->searchLatest(10))->toBe([])
        ->and($queue->enqueued)->toBe([]);
});
