<?php

function inProduction(array $overrides = []): void
{
    app()->detectEnvironment(fn () => 'production');

    config(array_merge([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'app.debug' => false,
        'database.connections.pgsql.host' => 'db.internal',
        'services.n8n.base_url' => 'https://n8n.internal',
        'services.delivery.destinations.crm.url' => 'https://crm.internal/webhooks/leads',
        'services.delivery.destinations.team.url' => 'https://n8n.internal/webhook/reformas-team',
        'services.delivery.destinations.customer.url' => 'https://n8n.internal/webhook/reformas-customer',
        'services.fake_crm.url' => null,
        'services.fake_crm.token' => null,
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

it('accepts fake crm when both url and token are configured in production', function () {
    inProduction([
        'services.fake_crm.url' => 'https://fake-crm.internal',
        'services.fake_crm.token' => 'un-token-secreto-1234',
    ]);

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
    'crm on localhost' => [['services.delivery.destinations.crm.url' => 'http://127.0.0.1:8080/webhooks/leads'], 'DELIVERY_CRM_URL falta o apunta a localhost.'],
    'crm missing' => [['services.delivery.destinations.crm.url' => null], 'DELIVERY_CRM_URL falta o apunta a localhost.'],
    'team notices on localhost' => [['services.delivery.destinations.team.url' => 'http://localhost:5678/webhook/reformas-team'], 'DELIVERY_TEAM_URL falta o apunta a localhost.'],
    'customer messages missing' => [['services.delivery.destinations.customer.url' => null], 'DELIVERY_CUSTOMER_URL falta o apunta a localhost.'],
    'fake crm url without token' => [['services.fake_crm.url' => 'https://fake-crm.internal', 'services.fake_crm.token' => null], 'FAKE_CRM_URL está definida pero falta FAKE_CRM_TOKEN.'],
]);
