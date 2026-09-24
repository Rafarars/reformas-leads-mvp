<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Reformas\Integrations\ApiClient\Application\Authenticate\ApiClientAuthenticator;
use Reformas\Integrations\ApiClient\Application\Authenticate\ApiClientAuthenticatorRequest;
use Reformas\Integrations\ApiClient\Domain\InvalidApiKeyError;
use Reformas\Integrations\ApiClient\Infrastructure\Persistence\Eloquent\ApiClientModel;

uses(RefreshDatabase::class);

it('creates the bot api client from the environment and it can authenticate', function () {
    $key = 'rl_'.str_repeat('a', 64);
    config(['services.telegram.bot_api_key' => $key]);

    $this->artisan('app:api-client:ensure-bot')
        ->expectsOutputToContain('Cliente API del bot de Telegram creado')
        ->doesntExpectOutputToContain($key)
        ->assertSuccessful();

    $model = ApiClientModel::query()->where('source', 'telegram-bot')->firstOrFail();
    expect($model->getAttribute('name'))->toBe('Telegram Bot')
        ->and($model->getAttribute('key_hash'))->toBe(hash('sha256', $key))
        ->and((bool) $model->getAttribute('active'))->toBeTrue();

    $authenticator = app(ApiClientAuthenticator::class);
    $response = $authenticator(new ApiClientAuthenticatorRequest($key));

    expect($response->source)->toBe('telegram-bot');

    // La autenticación HTTP con Bearer funciona de punta a punta:
    $this->postJson('/api/v1/telegram/link', [])->assertUnauthorized();
    $this->postJson('/api/v1/telegram/link', [], ['Authorization' => 'Bearer '.$key])->assertUnprocessable();
});

it('is idempotent and preserves the client when the key has not changed', function () {
    $key = 'rl_'.str_repeat('a', 64);
    config(['services.telegram.bot_api_key' => $key]);

    $this->artisan('app:api-client:ensure-bot')->assertSuccessful();

    $id = ApiClientModel::query()->where('source', 'telegram-bot')->value('id');

    $this->artisan('app:api-client:ensure-bot')
        ->expectsOutputToContain('Cliente API del bot de Telegram ya configurado')
        ->doesntExpectOutputToContain($key)
        ->assertSuccessful();

    expect(ApiClientModel::query()->where('source', 'telegram-bot')->count())->toBe(1)
        ->and(ApiClientModel::query()->where('source', 'telegram-bot')->value('id'))->toBe($id);
});

it('rotates the api key when the environment variable changes', function () {
    $firstKey = 'rl_'.str_repeat('a', 64);
    $secondKey = 'rl_'.str_repeat('b', 64);

    config(['services.telegram.bot_api_key' => $firstKey]);
    $this->artisan('app:api-client:ensure-bot')->assertSuccessful();

    config(['services.telegram.bot_api_key' => $secondKey]);
    $this->artisan('app:api-client:ensure-bot')
        ->expectsOutputToContain('Cliente API del bot de Telegram actualizado')
        ->doesntExpectOutputToContain($secondKey)
        ->assertSuccessful();

    expect(ApiClientModel::query()->where('source', 'telegram-bot')->count())->toBe(1)
        ->and(ApiClientModel::query()->where('source', 'telegram-bot')->value('key_hash'))->toBe(hash('sha256', $secondKey));

    $authenticator = app(ApiClientAuthenticator::class);
    expect($authenticator(new ApiClientAuthenticatorRequest($secondKey))->source)->toBe('telegram-bot');
    expect(fn () => $authenticator(new ApiClientAuthenticatorRequest($firstKey)))->toThrow(InvalidApiKeyError::class);
});

it('does nothing without the environment variable so startup never breaks', function (?string $rawKey) {
    config(['services.telegram.bot_api_key' => $rawKey]);

    $this->artisan('app:api-client:ensure-bot')
        ->expectsOutputToContain('Sin BOT_API_KEY')
        ->assertSuccessful();

    expect(ApiClientModel::query()->where('source', 'telegram-bot')->count())->toBe(0);
})->with([
    'null' => null,
    'empty string' => '',
    'only spaces' => '   ',
]);

it('fails cleanly without persisting anything if the key format is invalid', function () {
    config(['services.telegram.bot_api_key' => 'clave-invalida']);

    $this->artisan('app:api-client:ensure-bot')
        ->expectsOutputToContain('BOT_API_KEY no tiene un formato válido')
        ->doesntExpectOutputToContain('clave-invalida')
        ->assertFailed();

    expect(ApiClientModel::query()->where('source', 'telegram-bot')->count())->toBe(0);
});
