<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Reformas\Integrations\ApiClient\Domain\ApiClient;
use Reformas\Integrations\ApiClient\Domain\ApiClientId;
use Reformas\Integrations\ApiClient\Domain\ApiClientName;
use Reformas\Integrations\ApiClient\Domain\ApiClientRepository;
use Reformas\Integrations\ApiClient\Domain\ApiClientSource;
use Reformas\Integrations\ApiClient\Domain\ApiKeyHash;
use Reformas\Integrations\ApiClient\Infrastructure\Persistence\Eloquent\EloquentApiClientRepository;
use Reformas\Integrations\ApiClient\Infrastructure\Persistence\InMemory\InMemoryApiClientRepository;

uses(RefreshDatabase::class);

dataset('api client repositories', [
    'in memory' => fn () => new InMemoryApiClientRepository,
    'eloquent' => fn () => new EloquentApiClientRepository,
]);

function contractApiClient(): ApiClient
{
    return ApiClient::register(
        new ApiClientId('00000000-0000-7000-8000-000000000001'),
        new ApiClientName('Formulario web'),
        new ApiClientSource('web-form'),
        new ApiKeyHash(str_repeat('c', 64)),
        new DateTimeImmutable('2026-09-14T10:00:00+00:00'),
    );
}

it('finds a saved client by its key hash exactly as it was', function (ApiClientRepository $repository) {
    $client = contractApiClient();
    $expected = $client->toPrimitives();

    $repository->save($client);

    expect($repository->searchByKeyHash(new ApiKeyHash(str_repeat('c', 64)))?->toPrimitives())->toBe($expected);
})->with('api client repositories');

it('returns null for a hash nobody has', function (ApiClientRepository $repository) {
    $repository->save(contractApiClient());

    expect($repository->searchByKeyHash(new ApiKeyHash(str_repeat('d', 64))))->toBeNull();
})->with('api client repositories');

it('keeps a deactivation when saved again', function (ApiClientRepository $repository) {
    $client = contractApiClient();
    $repository->save($client);
    $client->deactivate();
    $repository->save($client);

    expect($repository->searchByKeyHash(new ApiKeyHash(str_repeat('c', 64)))?->isActive())->toBeFalse();
})->with('api client repositories');

it('finds a saved client by its source', function (ApiClientRepository $repository) {
    $client = contractApiClient();
    $expected = $client->toPrimitives();

    $repository->save($client);

    expect($repository->searchBySource(new ApiClientSource('web-form'))?->toPrimitives())->toBe($expected);
})->with('api client repositories');

it('returns null for a source nobody has', function (ApiClientRepository $repository) {
    $repository->save(contractApiClient());

    expect($repository->searchBySource(new ApiClientSource('telegram-bot')))->toBeNull();
})->with('api client repositories');
