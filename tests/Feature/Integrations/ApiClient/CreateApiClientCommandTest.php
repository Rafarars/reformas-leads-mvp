<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('creates a client and prints its key once', function () {
    Artisan::call('app:api-client:create', ['name' => 'Formulario web', 'source' => 'web-form']);
    $output = Artisan::output();

    preg_match('/rl_[a-f0-9]{64}/', $output, $matches);
    $row = DB::table('api_clients')->first();

    expect($matches)->toHaveCount(1)
        ->and($output)->toContain('Guarda esta clave')
        ->and($row->key_hash)->toBe(hash('sha256', $matches[0]))
        // La clave en claro no aparece en ninguna columna.
        ->and(json_encode($row))->not->toContain($matches[0]);
});

it('fails with a readable message when the source is not valid', function () {
    $this->artisan('app:api-client:create', ['name' => 'Formulario web', 'source' => 'Web Form'])
        ->expectsOutputToContain('minúsculas')
        ->assertFailed();

    expect(DB::table('api_clients')->count())->toBe(0);
});
