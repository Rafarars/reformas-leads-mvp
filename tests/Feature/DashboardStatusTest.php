<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows every service up', function () {
    Http::fake(['*/healthz' => Http::response(['status' => 'ok'])]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('systemStatus', ['database' => true, 'n8n' => true]));
});

it('reports n8n down when it answers with an error', function () {
    Http::fake(['*/healthz' => Http::response(status: 503)]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('systemStatus', ['database' => true, 'n8n' => false]));
});

// El panel tiene que cargar aunque n8n no conteste: es justo cuando más se necesita verlo.
it('reports n8n down when it does not answer', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('systemStatus', ['database' => true, 'n8n' => false]));
});
