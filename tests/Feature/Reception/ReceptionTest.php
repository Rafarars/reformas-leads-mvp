<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function receptionPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ana García',
        'phone' => '600 111 222',
        'email' => 'ana@example.com',
        'renovation_type' => 'bathroom',
        'zone' => 'Madrid',
        'budget' => 9_000,
        'description' => 'Cambiar bañera por plato de ducha',
    ], $overrides);
}

it('sends guests to the login page', function () {
    $this->get(route('reception'))->assertRedirect(route('login'));
    $this->post(route('reception.leads.store'), receptionPayload())->assertRedirect(route('login'));

    expect(DB::table('leads')->count())->toBe(0);
});

it('shows the form with the renovation types the domain accepts', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reception'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reception/create')
            ->where('renovationTypes', ['kitchen', 'bathroom', 'full_home', 'partial', 'other']));
});

it('registers a lead with the reception source', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('reception'))
        ->post(route('reception.leads.store'), receptionPayload())
        ->assertRedirect(route('reception'))
        ->assertSessionHas('receptionResult', fn (array $result) => $result['repeated'] === false && $result['contact_count'] === 1);

    $lead = DB::table('leads')->first();

    expect($lead->source)->toBe('reception')
        ->and($lead->phone)->toBe('+34600111222');
});

it('reports a repeated contact when the person already exists', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('reception.leads.store'), receptionPayload());
    $this->actingAs($user)
        ->post(route('reception.leads.store'), receptionPayload(['email' => 'otra@example.com']))
        ->assertSessionHas('receptionResult', fn (array $result) => $result['repeated'] === true && $result['contact_count'] === 2);

    expect(DB::table('leads')->count())->toBe(1);
});

it('shows missing fields as form errors', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('reception'))
        ->post(route('reception.leads.store'), [])
        ->assertRedirect(route('reception'))
        ->assertSessionHasErrors(['name', 'phone', 'email', 'renovation_type']);
});

it('shows a domain rule under the field that broke it', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('reception'))
        ->post(route('reception.leads.store'), receptionPayload(['phone' => 'no-soy-un-telefono']))
        ->assertRedirect(route('reception'))
        ->assertSessionHasErrors(['phone' => 'El teléfono no es válido.']);

    expect(DB::table('leads')->count())->toBe(0);
});
