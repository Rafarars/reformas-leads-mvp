<?php

use Illuminate\Support\Facades\DB;

it('reports healthy when the database answers', function () {
    $this->getJson('/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'database' => 'up']);
});

it('returns 503 when the database does not answer', function () {
    DB::shouldReceive('select')->once()->andThrow(new RuntimeException('connection refused'));

    $this->getJson('/health')
        ->assertStatus(503)
        ->assertExactJson(['status' => 'error', 'database' => 'down']);
});

// Las sesiones se guardan en la base: si pasara por el grupo web, una base caída
// rompería la sesión antes del chequeo y la respuesta sería 500, no 503.
it('does not start a session', function () {
    $this->get('/health')->assertCookieMissing(config('session.cookie'));
});
