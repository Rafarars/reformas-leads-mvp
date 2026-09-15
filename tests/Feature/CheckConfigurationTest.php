<?php

function inProduction(array $overrides = []): void
{
    app()->detectEnvironment(fn () => 'production');

    config(array_merge([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'app.debug' => false,
        'database.connections.pgsql.host' => 'db.internal',
        'services.n8n.base_url' => 'https://n8n.internal',
        'services.delivery.webhook_url' => 'https://crm.internal/webhooks/leads',
    ], $overrides));
}

it('does not require anything outside production', function () {
    config(['database.connections.pgsql.host' => '127.0.0.1']);

    $this->artisan('app:check-config')->assertSuccessful();
});

it('accepts a valid production configuration', function () {
    inProduction();

    $this->artisan('app:check-config')
        ->expectsOutputToContain('Configuración válida.')
        ->assertSuccessful();
});

it('refuses to start in production with a broken configuration', function (array $overrides, string $problem) {
    inProduction($overrides);

    $this->artisan('app:check-config')
        ->expectsOutputToContain($problem)
        ->assertFailed();
})->with([
    'missing app key' => [['app.key' => ''], 'APP_KEY está vacía.'],
    'debug enabled' => [['app.debug' => true], 'APP_DEBUG está activo'],
    'database on localhost' => [['database.connections.pgsql.host' => '127.0.0.1'], 'DB_HOST apunta a localhost.'],
    'n8n on localhost' => [['services.n8n.base_url' => 'http://localhost:5678'], 'N8N_BASE_URL falta o apunta a localhost.'],
    'n8n missing' => [['services.n8n.base_url' => ''], 'N8N_BASE_URL falta o apunta a localhost.'],
    'delivery on localhost' => [['services.delivery.webhook_url' => 'http://127.0.0.1:8080/webhooks/leads'], 'DELIVERY_WEBHOOK_URL falta o apunta a localhost.'],
    'delivery missing' => [['services.delivery.webhook_url' => null], 'DELIVERY_WEBHOOK_URL falta o apunta a localhost.'],
]);
