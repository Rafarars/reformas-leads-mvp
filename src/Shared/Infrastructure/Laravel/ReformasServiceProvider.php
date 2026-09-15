<?php

namespace Reformas\Shared\Infrastructure\Laravel;

use Illuminate\Support\ServiceProvider;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use Reformas\Delivery\Outbox\Infrastructure\OutboxEventBus;
use Reformas\Delivery\Outbox\Infrastructure\Persistence\Eloquent\EloquentOutboxRepository;
use Reformas\Integrations\ApiClient\Domain\ApiClientRepository;
use Reformas\Integrations\ApiClient\Domain\ApiKeyGenerator;
use Reformas\Integrations\ApiClient\Infrastructure\Persistence\Eloquent\EloquentApiClientRepository;
use Reformas\Integrations\ApiClient\Infrastructure\RandomApiKeyGenerator;
use Reformas\Leads\Lead\Domain\LeadRepository;
use Reformas\Leads\Lead\Infrastructure\Persistence\Eloquent\EloquentLeadRepository;
use Reformas\Shared\Domain\Clock;
use Reformas\Shared\Domain\EventBus;
use Reformas\Shared\Domain\Transaction;
use Reformas\Shared\Domain\UuidGenerator;
use Reformas\Shared\Infrastructure\LaravelTransaction;
use Reformas\Shared\Infrastructure\LaravelUuidGenerator;
use Reformas\Shared\Infrastructure\SystemClock;

// Único lugar donde se decide qué adaptador resuelve cada puerto.
final class ReformasServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        Clock::class => SystemClock::class,
        UuidGenerator::class => LaravelUuidGenerator::class,
        Transaction::class => LaravelTransaction::class,
        LeadRepository::class => EloquentLeadRepository::class,
        ApiClientRepository::class => EloquentApiClientRepository::class,
        ApiKeyGenerator::class => RandomApiKeyGenerator::class,
        EventBus::class => OutboxEventBus::class,
        OutboxRepository::class => EloquentOutboxRepository::class,
    ];
}
