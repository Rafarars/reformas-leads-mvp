<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreator;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreatorRequest;
use Reformas\Shared\Domain\DomainEvent;
use Reformas\Shared\Domain\EventBus;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = app(ApiClientCreator::class)(new ApiClientCreatorRequest('Formulario web', 'web-form'))->apiKey;
});

function sendLead(string $apiKey, array $overrides = []): TestResponse
{
    return test()->postJson('/api/v1/leads', array_merge([
        'name' => 'Ana García',
        'phone' => '600111222',
        'email' => 'ana@example.com',
        'renovation_type' => 'kitchen',
    ], $overrides), ['Authorization' => 'Bearer '.$apiKey]);
}

it('writes the notice of every reception to the outbox', function () {
    $first = sendLead($this->apiKey)->assertCreated();
    sendLead($this->apiKey, ['email' => 'otra@example.com'])->assertOk();

    $messages = DB::table('outbox_messages')->orderBy('created_at')->orderBy('id')->get();
    $payloads = $messages->map(fn ($message) => json_decode($message->payload, true));

    expect($messages)->toHaveCount(2)
        ->and($messages->pluck('event_name')->unique()->all())->toBe(['lead.received'])
        ->and($messages->pluck('aggregate_id')->unique()->all())->toBe([$first->json('id')])
        ->and($messages->pluck('status')->unique()->all())->toBe(['pending'])
        ->and($payloads->pluck('repeated')->sort()->values()->all())->toBe([false, true]);
});

// La garantía del outbox: si no se puede guardar el aviso, tampoco se guarda el lead.
it('keeps the lead and its notice together: if the notice cannot be written, nothing is saved', function () {
    $this->app->instance(EventBus::class, new class implements EventBus
    {
        public function publish(DomainEvent ...$events): void
        {
            throw new RuntimeException('No se pudo escribir en el outbox');
        }
    });

    sendLead($this->apiKey)->assertServerError();

    expect(DB::table('leads')->count())->toBe(0)
        ->and(DB::table('lead_history')->count())->toBe(0)
        ->and(DB::table('outbox_messages')->count())->toBe(0);
});
