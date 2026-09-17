<?php

use Reformas\Delivery\Outbox\Infrastructure\ConfigDeliveryRouter;
use Reformas\Delivery\Outbox\Infrastructure\OutboxEventBus;
use Reformas\Delivery\Outbox\Infrastructure\Persistence\InMemory\InMemoryOutboxRepository;
use Reformas\Shared\Domain\DomainEvent;
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

function routedBus(array $eventsByDestination, InMemoryOutboxRepository $outbox, SpyDeliveryQueue $queue): OutboxEventBus
{
    return new OutboxEventBus($outbox, new SequentialUuidGenerator, new FixedClock, $queue, new ConfigDeliveryRouter($eventsByDestination));
}

it('routes each event only to the destinations subscribed to it', function () {
    $router = new ConfigDeliveryRouter(['crm' => ['lead.received'], 'team' => ['lead.classified'], 'customer' => ['lead.customer_message_approved']]);

    expect($router->destinationsFor('lead.received'))->toBe(['crm'])
        ->and($router->destinationsFor('lead.classified'))->toBe(['team'])
        ->and($router->destinationsFor('unknown'))->toBe([])
        ->and($router->destinations())->toBe(['crm', 'team', 'customer']);
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

it('stores nothing for an event no destination listens to', function () {
    $outbox = new InMemoryOutboxRepository;
    $queue = new SpyDeliveryQueue;

    routedBus(['crm' => ['lead.received']], $outbox, $queue)->publish(new RoutedTestEvent('Ana'));

    expect($outbox->searchLatest(10))->toBe([])
        ->and($queue->enqueued)->toBe([]);
});
