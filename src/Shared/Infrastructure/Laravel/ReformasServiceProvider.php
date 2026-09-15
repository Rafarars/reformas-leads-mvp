<?php

namespace Reformas\Shared\Infrastructure\Laravel;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Reformas\Delivery\Outbox\Domain\CircuitBreaker;
use Reformas\Delivery\Outbox\Domain\CircuitBreakerStore;
use Reformas\Delivery\Outbox\Domain\DeliveryQueue;
use Reformas\Delivery\Outbox\Domain\MessageDeliverer;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use Reformas\Delivery\Outbox\Domain\RetryPolicy;
use Reformas\Delivery\Outbox\Infrastructure\Cache\CacheCircuitBreakerStore;
use Reformas\Delivery\Outbox\Infrastructure\Http\WebhookMessageDeliverer;
use Reformas\Delivery\Outbox\Infrastructure\OutboxEventBus;
use Reformas\Delivery\Outbox\Infrastructure\Persistence\Eloquent\EloquentOutboxRepository;
use Reformas\Delivery\Outbox\Infrastructure\Queue\LaravelDeliveryQueue;
use Reformas\Integrations\ApiClient\Domain\ApiClientRepository;
use Reformas\Integrations\ApiClient\Domain\ApiKeyGenerator;
use Reformas\Integrations\ApiClient\Infrastructure\Persistence\Eloquent\EloquentApiClientRepository;
use Reformas\Integrations\ApiClient\Infrastructure\RandomApiKeyGenerator;
use Reformas\Leads\Classification\Application\Generate\ClassifyLeadOnLeadReceived;
use Reformas\Leads\Classification\Application\Generate\LeadClassificationGenerator;
use Reformas\Leads\Classification\Domain\ClassificationQueue;
use Reformas\Leads\Classification\Domain\LeadClassificationRepository;
use Reformas\Leads\Classification\Domain\LeadClassifier;
use Reformas\Leads\Classification\Infrastructure\Gemini\GeminiLeadClassifier;
use Reformas\Leads\Classification\Infrastructure\Persistence\Eloquent\EloquentLeadClassificationRepository;
use Reformas\Leads\Classification\Infrastructure\Queue\ClassifyLeadJob;
use Reformas\Leads\Classification\Infrastructure\Queue\LaravelClassificationQueue;
use Reformas\Leads\Lead\Domain\LeadRepository;
use Reformas\Leads\Lead\Infrastructure\Persistence\Eloquent\EloquentLeadRepository;
use Reformas\Shared\Domain\Clock;
use Reformas\Shared\Domain\EventBus;
use Reformas\Shared\Domain\Random;
use Reformas\Shared\Domain\Transaction;
use Reformas\Shared\Domain\UuidGenerator;
use Reformas\Shared\Infrastructure\LaravelTransaction;
use Reformas\Shared\Infrastructure\LaravelUuidGenerator;
use Reformas\Shared\Infrastructure\PhpRandom;
use Reformas\Shared\Infrastructure\SubscribersEventBus;
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
        OutboxRepository::class => EloquentOutboxRepository::class,
        CircuitBreakerStore::class => CacheCircuitBreakerStore::class,
        Random::class => PhpRandom::class,
        DeliveryQueue::class => LaravelDeliveryQueue::class,
        LeadClassificationRepository::class => EloquentLeadClassificationRepository::class,
        ClassificationQueue::class => LaravelClassificationQueue::class,
    ];

    public function register(): void
    {
        $this->app->bind(EventBus::class, fn (Application $app): EventBus => new SubscribersEventBus(
            $app->make(OutboxEventBus::class),
            [$app->make(ClassifyLeadOnLeadReceived::class)],
        ));

        $this->app->bind(LeadClassifier::class, function (): LeadClassifier {
            $apiKey = config('services.gemini.api_key');

            return new GeminiLeadClassifier(
                is_string($apiKey) ? $apiKey : null,
                config()->string('services.gemini.model'),
                config()->string('services.gemini.base_url'),
                config()->integer('services.gemini.timeout_seconds'),
            );
        });

        $this->app->bind(LeadClassificationGenerator::class, fn (Application $app): LeadClassificationGenerator => new LeadClassificationGenerator(
            $app->make(LeadRepository::class),
            $app->make(LeadClassificationRepository::class),
            $app->make(LeadClassifier::class),
            $app->make(Clock::class),
            config()->float('services.classification.min_confidence'),
        ));

        $this->app->bind(MessageDeliverer::class, function (): MessageDeliverer {
            $url = config('services.delivery.webhook_url');

            return new WebhookMessageDeliverer(is_string($url) ? $url : null, config()->integer('services.delivery.timeout_seconds'));
        });

        $this->app->bind(RetryPolicy::class, fn (Application $app): RetryPolicy => new RetryPolicy(
            $app->make(Random::class),
            config()->integer('services.delivery.base_delay_seconds'),
            config()->integer('services.delivery.max_delay_seconds'),
            config()->integer('services.delivery.give_up_after_seconds'),
        ));

        $this->app->bind(CircuitBreaker::class, fn (Application $app): CircuitBreaker => new CircuitBreaker(
            $app->make(CircuitBreakerStore::class),
            config()->integer('services.delivery.failure_threshold'),
            config()->integer('services.delivery.cooldown_seconds'),
        ));
    }

    public function boot(): void
    {
        RateLimiter::for(ClassifyLeadJob::RATE_LIMITER, fn (): Limit => Limit::perMinute(config()->integer('services.gemini.requests_per_minute')));
    }
}
