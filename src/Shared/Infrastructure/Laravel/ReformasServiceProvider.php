<?php

namespace Reformas\Shared\Infrastructure\Laravel;

use Illuminate\Support\ServiceProvider;
use Reformas\Leads\Lead\Domain\LeadRepository;
use Reformas\Leads\Lead\Infrastructure\Persistence\Eloquent\EloquentLeadRepository;
use Reformas\Shared\Domain\Clock;
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
    ];
}
