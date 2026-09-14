<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreator;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreatorRequest;
use Reformas\Leads\Lead\Domain\EmailAddress;
use Reformas\Leads\Lead\Domain\Lead;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadRepository;
use Reformas\Leads\Lead\Domain\PhoneNumber;
use Reformas\Leads\Lead\Infrastructure\Persistence\Eloquent\EloquentLeadRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = apiClientKey('Formulario web', 'web-form');
});

function apiClientKey(string $name, string $source): string
{
    return app(ApiClientCreator::class)(new ApiClientCreatorRequest($name, $source))->apiKey;
}

function leadPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ana García',
        'phone' => '600 111 222',
        'email' => 'ana@example.com',
        'renovation_type' => 'kitchen',
        'zone' => 'Madrid',
        'budget' => 18_000,
        'description' => 'Reforma de cocina completa',
    ], $overrides);
}

function postLead(array $payload, ?string $apiKey, array $headers = []): TestResponse
{
    if ($apiKey !== null) {
        $headers['Authorization'] = 'Bearer '.$apiKey;
    }

    return test()->postJson('/api/v1/leads', $payload, $headers);
}

// ---------------------------------------------------------------- autenticación

it('rejects a request without an api key', function () {
    postLead(leadPayload(), null)
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer')
        ->assertExactJson(['type' => 'invalid_api_key', 'message' => 'La API key no es válida.']);

    expect(DB::table('leads')->count())->toBe(0);
});

it('rejects an api key nobody has', function () {
    postLead(leadPayload(), 'rl_'.str_repeat('f', 64))->assertUnauthorized()->assertJsonPath('type', 'invalid_api_key');
});

// ---------------------------------------------------------------- recepción

it('receives a new lead and answers 201', function () {
    $response = postLead(leadPayload(), $this->apiKey)
        ->assertCreated()
        ->assertJsonPath('status', 'received')
        ->assertJsonPath('contact_count', 1)
        ->assertJsonPath('repeated', false);

    $stored = DB::table('leads')->where('id', $response->json('id'))->first();

    expect($stored->phone)->toBe('+34600111222')
        ->and($stored->source)->toBe('web-form')
        ->and(DB::table('lead_history')->where('lead_id', $stored->id)->count())->toBe(1);
});

it('takes the source from the api client, not from the body', function () {
    $response = postLead(leadPayload(['source' => 'facebook-ads']), $this->apiKey)->assertCreated();

    expect(DB::table('leads')->where('id', $response->json('id'))->value('source'))->toBe('web-form');
});

it('answers 200 with the same lead when the same person contacts again', function () {
    $first = postLead(leadPayload(), $this->apiKey)->assertCreated();

    postLead(leadPayload(['email' => 'otro@example.com', 'phone' => '+34 600 111 222']), $this->apiKey)
        ->assertOk()
        ->assertJsonPath('id', $first->json('id'))
        ->assertJsonPath('repeated', true)
        ->assertJsonPath('contact_count', 2);

    expect(DB::table('leads')->count())->toBe(1);
});

it('accepts a lead without the optional fields', function () {
    postLead(leadPayload(['zone' => null, 'budget' => null, 'description' => '']), $this->apiKey)->assertCreated();
});

// ---------------------------------------------------------------- errores

it('reports missing fields as validation_failed', function () {
    postLead([], $this->apiKey)
        ->assertUnprocessable()
        ->assertJsonPath('type', 'validation_failed')
        ->assertJsonValidationErrors(['name', 'phone', 'email', 'renovation_type']);
});

it('reports a domain rule with its own type and without echoing the input', function () {
    postLead(leadPayload(['phone' => 'no-soy-un-telefono']), $this->apiKey)
        ->assertUnprocessable()
        ->assertExactJson(['type' => 'invalid_phone_number', 'message' => 'El teléfono no es válido.']);
});

// ---------------------------------------------------------------- idempotencia

it('replays the stored answer for a retried request without counting a new contact', function () {
    $headers = ['Idempotency-Key' => 'retry-0001'];

    $first = postLead(leadPayload(), $this->apiKey, $headers)->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
    $second = postLead(leadPayload(), $this->apiKey, $headers)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json())->toBe($first->json())
        ->and(DB::table('leads')->value('contact_count'))->toBe(1)
        ->and(DB::table('lead_history')->count())->toBe(1);
});

it('rejects reusing an idempotency key with a different body', function () {
    postLead(leadPayload(), $this->apiKey, ['Idempotency-Key' => 'retry-0001'])->assertCreated();

    postLead(leadPayload(['name' => 'Otra Persona']), $this->apiKey, ['Idempotency-Key' => 'retry-0001'])
        ->assertUnprocessable()
        ->assertJsonPath('type', 'idempotency_key_reused');
});

it('keeps idempotency keys separate for each client', function () {
    $otherKey = apiClientKey('Facebook Lead Ads', 'facebook-ads');

    postLead(leadPayload(), $this->apiKey, ['Idempotency-Key' => 'retry-0001'])->assertCreated();
    postLead(leadPayload(['phone' => '611222333', 'email' => 'luis@example.com']), $otherKey, ['Idempotency-Key' => 'retry-0001'])
        ->assertCreated()
        ->assertHeaderMissing('Idempotent-Replayed');
});

it('rejects a malformed idempotency key', function () {
    postLead(leadPayload(), $this->apiKey, ['Idempotency-Key' => 'abc'])
        ->assertUnprocessable()
        ->assertJsonPath('type', 'invalid_idempotency_key');
});

it('does not memorize a server error, so the same key can be retried', function () {
    $this->app->instance(LeadRepository::class, new class implements LeadRepository
    {
        public function save(Lead $lead): void
        {
            throw new RuntimeException('La base no responde');
        }

        public function search(LeadId $id): ?Lead
        {
            return null;
        }

        public function searchByContact(PhoneNumber $phone, EmailAddress $email): ?Lead
        {
            return null;
        }

        public function history(LeadId $id): array
        {
            return [];
        }
    });

    postLead(leadPayload(), $this->apiKey, ['Idempotency-Key' => 'retry-0001'])->assertServerError();

    $this->app->instance(LeadRepository::class, new EloquentLeadRepository);

    postLead(leadPayload(), $this->apiKey, ['Idempotency-Key' => 'retry-0001'])
        ->assertCreated()
        ->assertHeaderMissing('Idempotent-Replayed');
});

// ---------------------------------------------------------------- límite de peticiones

it('limits requests per client without affecting the others', function () {
    config(['services.api.requests_per_minute' => 2]);
    $otherKey = apiClientKey('Facebook Lead Ads', 'facebook-ads');

    postLead(leadPayload(['phone' => '600000001', 'email' => 'a1@example.com']), $this->apiKey)->assertCreated();
    postLead(leadPayload(['phone' => '600000002', 'email' => 'a2@example.com']), $this->apiKey)->assertCreated();

    postLead(leadPayload(['phone' => '600000003', 'email' => 'a3@example.com']), $this->apiKey)
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('type', 'too_many_requests');

    postLead(leadPayload(['phone' => '600000004', 'email' => 'b1@example.com']), $otherKey)->assertCreated();
});
