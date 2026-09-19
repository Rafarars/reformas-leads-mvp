<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreator;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreatorRequest;
use Reformas\Leads\Attachment\Domain\AttachmentSize;
use Reformas\Leads\Classification\Domain\LeadClassification;
use Reformas\Leads\Classification\Domain\LeadClassificationRepository;
use Reformas\Leads\Classification\Domain\LeadContact;
use Reformas\Leads\Lead\Domain\Lead;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadRepository;
use Tests\Unit\Leads\Lead\Domain\LeadSubmissionMother;

uses(RefreshDatabase::class);

const ATTACHED_LEAD_ID = '00000000-0000-7000-8000-000000000001';

beforeEach(function () {
    // Disco falso: las pruebas no escriben en el disco real del proyecto.
    Storage::fake('local');
    app(LeadRepository::class)->save(Lead::receive(new LeadId(ATTACHED_LEAD_ID), LeadSubmissionMother::create(), new DateTimeImmutable));
});

function postAttachment(string $leadId, UploadedFile $file, string $kind = 'photo'): TestResponse
{
    $apiKey = app(ApiClientCreator::class)(new ApiClientCreatorRequest('Formulario web', 'web-form'))->apiKey;

    return test()->post("/api/v1/leads/{$leadId}/attachments", ['kind' => $kind, 'file' => $file], [
        'Authorization' => 'Bearer '.$apiKey,
        'Accept' => 'application/json',
    ]);
}

it('accepts a photo through the public API and stores it out of reach of the browser', function () {
    $response = postAttachment(ATTACHED_LEAD_ID, UploadedFile::fake()->image('cocina.jpg'));

    $response->assertCreated()->assertJsonPath('file_name', 'cocina.jpg');

    $stored = DB::table('lead_attachments')->where('lead_id', ATTACHED_LEAD_ID)->first();
    expect($stored->kind)->toBe('photo')
        ->and($stored->path)->toBe('leads/'.ATTACHED_LEAD_ID.'/'.$stored->id.'.jpg');
    Storage::disk('local')->assertExists($stored->path);
});

it('refuses a format that is not a photo or a plan', function () {
    postAttachment(ATTACHED_LEAD_ID, UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'))
        ->assertStatus(422)
        ->assertJsonPath('type', 'invalid_attachment_content_type');

    expect(DB::table('lead_attachments')->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuses a file over the size limit before reading it', function () {
    postAttachment(ATTACHED_LEAD_ID, UploadedFile::fake()->create('plano.pdf', (AttachmentSize::MAX_BYTES / 1024) + 1, 'application/pdf'))
        ->assertStatus(422)
        ->assertJsonPath('type', 'validation_failed');

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuses to attach anything to a lead that does not exist', function () {
    postAttachment('00000000-0000-7000-8000-000000000099', UploadedFile::fake()->image('cocina.jpg'))
        ->assertNotFound()
        ->assertJsonPath('type', 'lead_not_found');

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('needs an API key', function () {
    test()->post('/api/v1/leads/'.ATTACHED_LEAD_ID.'/attachments', ['kind' => 'photo', 'file' => UploadedFile::fake()->image('cocina.jpg')], ['Accept' => 'application/json'])
        ->assertUnauthorized();
});

it('uploads from the panel and serves the file only to a signed-in user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('classification'))
        ->post(route('leads.attachments.store', ['lead' => ATTACHED_LEAD_ID]), ['kind' => 'plan', 'file' => UploadedFile::fake()->create('plano.pdf', 20, 'application/pdf')])
        ->assertRedirect(route('classification'))
        ->assertSessionHasNoErrors();

    $stored = DB::table('lead_attachments')->where('lead_id', ATTACHED_LEAD_ID)->first();

    $this->actingAs($user)
        ->get(route('attachments.show', ['attachment' => $stored->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="plano.pdf"');
});

it('shows the attachments next to the classification of each lead', function () {
    $user = User::factory()->create();
    // El panel lista clasificaciones: las fotos se revisan junto a lo que propuso la IA.
    app(LeadClassificationRepository::class)->save(LeadClassification::unclassified(
        new LeadId(ATTACHED_LEAD_ID),
        'Pendiente de revisar',
        new LeadContact('Ana García', 'ana@example.com', '+34600111222'),
        new DateTimeImmutable,
    ));
    $this->actingAs($user)->post(route('leads.attachments.store', ['lead' => ATTACHED_LEAD_ID]), ['kind' => 'photo', 'file' => UploadedFile::fake()->image('cocina.jpg')]);

    $this->actingAs($user)
        ->get(route('classification'))
        ->assertOk()
        ->assertSee('cocina.jpg');
});

it('sends guests to the login page instead of serving a file', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('leads.attachments.store', ['lead' => ATTACHED_LEAD_ID]), ['kind' => 'photo', 'file' => UploadedFile::fake()->image('cocina.jpg')]);
    $stored = DB::table('lead_attachments')->where('lead_id', ATTACHED_LEAD_ID)->first();

    // Sesión cerrada: el disco es privado, así que sin sesión no se sirve nada.
    auth()->logout();

    $this->get(route('attachments.show', ['attachment' => $stored->id]))->assertRedirect(route('login'));
    $this->post(route('leads.attachments.store', ['lead' => ATTACHED_LEAD_ID]), ['kind' => 'photo'])->assertRedirect(route('login'));
});
