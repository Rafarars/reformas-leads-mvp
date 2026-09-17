<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Reformas\Delivery\Outbox\Infrastructure\Queue\DeliverOutboxMessageJob;
use Reformas\Leads\Classification\Domain\ClassifierUnavailableException;
use Reformas\Leads\Classification\Infrastructure\Queue\ClassifyLeadJob;
use Tests\Unit\Leads\Lead\LeadReceiverRequestMother;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.base_url' => 'https://gemini.test/v1beta',
    ]);
    $this->user = User::factory()->create();
});

function receiveLeadFromPanel(object $test, array $overrides = []): string
{
    $request = LeadReceiverRequestMother::create();

    $test->actingAs($test->user)->post(route('reception.leads.store'), [
        'name' => $request->name,
        'phone' => $request->phone,
        'email' => $request->email,
        'renovation_type' => 'bathroom',
        'description' => 'Fuga bajo la ducha',
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return (string) DB::table('leads')->where('email', $overrides['email'] ?? $request->email)->value('id');
}

function runClassificationJob(string $leadId): void
{
    app()->call([(new ClassifyLeadJob($leadId))->withFakeQueueInteractions(), 'handle']);
}

it('queues the classification when a lead arrives, and not again for a repeated contact', function () {
    receiveLeadFromPanel($this, ['email' => 'ana@example.com', 'phone' => '600111222']);
    receiveLeadFromPanel($this, ['email' => 'ana@example.com', 'phone' => '600111222']);

    Queue::assertPushed(ClassifyLeadJob::class, 1);
});

it('classifies the lead with the model through the queue job', function () {
    Http::fake(['gemini.test/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['category' => 'bathroom', 'urgency' => 'high', 'confidence' => 0.9, 'reason' => 'Fuga activa.', 'summary' => 'Cambiar el plato de ducha.', 'missing_information' => ['Medidas'], 'contradictions' => [], 'customer_message' => 'Hola {nombre}, gracias.'])]]]]]])]);
    $leadId = receiveLeadFromPanel($this);

    runClassificationJob($leadId);

    expect(DB::table('lead_classifications')->where('lead_id', $leadId)->first())
        ->status->toBe('classified')
        ->category->toBe('bathroom');
});

it('keeps the lead and lets the queue retry when the model is down', function () {
    Http::fake(['gemini.test/*' => Http::response([], 503)]);
    $leadId = receiveLeadFromPanel($this);

    expect(fn () => runClassificationJob($leadId))->toThrow(ClassifierUnavailableException::class)
        ->and(DB::table('leads')->where('id', $leadId)->exists())->toBeTrue()
        ->and(DB::table('lead_classifications')->count())->toBe(0);
});

it('sends the lead to review when the job gives up', function () {
    $leadId = receiveLeadFromPanel($this);

    (new ClassifyLeadJob($leadId))->failed(new RuntimeException('HTTP 503'));

    expect(DB::table('lead_classifications')->where('lead_id', $leadId)->value('status'))->toBe('needs_review');
});

it('sends guests to the login page', function () {
    $this->get(route('classification'))->assertRedirect(route('login'));
    $this->post(route('classification.review', ['lead' => '00000000-0000-7000-8000-000000000001']))->assertRedirect(route('login'));
    $this->post(route('classification.draft.approve', ['lead' => '00000000-0000-7000-8000-000000000001']))->assertRedirect(route('login'));
});

it('shows the classifications next to the lead and records a human review', function () {
    $leadId = receiveLeadFromPanel($this);
    (new ClassifyLeadJob($leadId))->failed(new RuntimeException('HTTP 503'));

    $this->actingAs($this->user)
        ->get(route('classification'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('classification/index')
            ->where('classifications.0.status', 'needs_review')
            ->where('classifications.0.lead.description', 'Fuga bajo la ducha')
            ->has('categories', 6)
            ->has('urgencies', 3));

    $this->actingAs($this->user)
        ->from(route('classification'))
        ->post(route('classification.review', ['lead' => $leadId]), ['category' => 'installations', 'urgency' => 'high'])
        ->assertRedirect(route('classification'))
        ->assertSessionHasNoErrors();

    expect(DB::table('lead_classifications')->where('lead_id', $leadId)->first())
        ->status->toBe('reviewed')
        ->category->toBe('installations')
        ->reviewed_by->toBe($this->user->email);
});

it('rejects a review with values outside the contract', function () {
    $leadId = receiveLeadFromPanel($this);
    (new ClassifyLeadJob($leadId))->failed(new RuntimeException('HTTP 503'));

    $this->actingAs($this->user)
        ->post(route('classification.review', ['lead' => $leadId]), ['category' => 'roof', 'urgency' => 'high'])
        ->assertSessionHasErrors('category');
});

it('approves the draft from the panel and leaves the notice in the outbox', function () {
    Http::fake(['gemini.test/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['category' => 'bathroom', 'urgency' => 'high', 'confidence' => 0.9, 'reason' => 'Fuga activa.', 'summary' => 'Cambiar el plato de ducha.', 'missing_information' => ['Medidas'], 'contradictions' => [], 'customer_message' => 'Hola {nombre}, gracias.'])]]]]]])]);
    $leadId = receiveLeadFromPanel($this);
    runClassificationJob($leadId);

    $this->actingAs($this->user)
        ->from(route('classification'))
        ->post(route('classification.draft.approve', ['lead' => $leadId]), ['message' => 'Hola Ana, gracias. ¿Nos mandas fotos?'])
        ->assertRedirect(route('classification'))
        ->assertSessionHasNoErrors();

    expect(DB::table('lead_classifications')->where('lead_id', $leadId)->first())
        ->draft_status->toBe('approved')
        ->draft_message->toBe('Hola Ana, gracias. ¿Nos mandas fotos?')
        ->and(DB::table('outbox_messages')->where('event_name', 'lead.customer_message_approved')->where('aggregate_id', $leadId)->exists())->toBeTrue();
    // Recibido (CRM), clasificado (equipo) y aprobado (cliente): un mensaje por destino.
    Queue::assertPushed(DeliverOutboxMessageJob::class, 3);

    $this->actingAs($this->user)
        ->post(route('classification.draft.approve', ['lead' => $leadId]), ['message' => 'Otra vez'])
        ->assertSessionHasErrors(['message' => 'Este lead no tiene un borrador pendiente de aprobar.']);
});
