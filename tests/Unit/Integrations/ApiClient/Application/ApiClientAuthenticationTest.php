<?php

use Reformas\Integrations\ApiClient\Application\Authenticate\ApiClientAuthenticator;
use Reformas\Integrations\ApiClient\Application\Authenticate\ApiClientAuthenticatorRequest;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreator;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreatorRequest;
use Reformas\Integrations\ApiClient\Domain\ApiKeyHash;
use Reformas\Integrations\ApiClient\Domain\InvalidApiClientSourceError;
use Reformas\Integrations\ApiClient\Domain\InvalidApiKeyError;
use Reformas\Integrations\ApiClient\Domain\PlainApiKey;
use Reformas\Integrations\ApiClient\Infrastructure\Persistence\InMemory\InMemoryApiClientRepository;
use Tests\Unit\Integrations\ApiClient\FixedApiKeyGenerator;
use Tests\Unit\Shared\FixedClock;
use Tests\Unit\Shared\SequentialUuidGenerator;

beforeEach(function () {
    $this->clients = new InMemoryApiClientRepository;
    $this->create = new ApiClientCreator($this->clients, new FixedApiKeyGenerator, new SequentialUuidGenerator, new FixedClock);
    $this->authenticate = new ApiClientAuthenticator($this->clients);
});

it('creates a client and hands over its key only once', function () {
    $response = ($this->create)(new ApiClientCreatorRequest('Formulario web', 'web-form'));
    $stored = $this->clients->searchByKeyHash((new PlainApiKey(FixedApiKeyGenerator::KEY))->hash())?->toPrimitives();

    expect($response->apiKey)->toBe(FixedApiKeyGenerator::KEY)
        ->and($stored['key_hash'])->not->toBe(FixedApiKeyGenerator::KEY)
        ->and($stored['active'])->toBeTrue()
        ->and($stored['source'])->toBe('web-form');
});

it('rejects a client whose source is not a slug', function () {
    ($this->create)(new ApiClientCreatorRequest('Formulario web', 'Web Form'));
})->throws(InvalidApiClientSourceError::class);

it('identifies the client and its source from its key', function () {
    $created = ($this->create)(new ApiClientCreatorRequest('Facebook Lead Ads', 'facebook-ads'));

    $response = ($this->authenticate)(new ApiClientAuthenticatorRequest($created->apiKey));

    expect($response->id)->toBe($created->id)->and($response->source)->toBe('facebook-ads');
});

it('rejects a key nobody has', function () {
    ($this->create)(new ApiClientCreatorRequest('Formulario web', 'web-form'));

    ($this->authenticate)(new ApiClientAuthenticatorRequest('rl_'.str_repeat('b', 64)));
})->throws(InvalidApiKeyError::class);

it('rejects the key of a deactivated client with the same error', function () {
    ($this->create)(new ApiClientCreatorRequest('Formulario web', 'web-form'));
    $client = $this->clients->searchByKeyHash(new ApiKeyHash(hash('sha256', FixedApiKeyGenerator::KEY)));
    $client->deactivate();
    $this->clients->save($client);

    ($this->authenticate)(new ApiClientAuthenticatorRequest(FixedApiKeyGenerator::KEY));
})->throws(InvalidApiKeyError::class);
