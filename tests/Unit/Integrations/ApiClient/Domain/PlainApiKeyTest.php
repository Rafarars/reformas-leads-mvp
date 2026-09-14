<?php

use Reformas\Integrations\ApiClient\Domain\InvalidApiKeyError;
use Reformas\Integrations\ApiClient\Domain\PlainApiKey;
use Reformas\Integrations\ApiClient\Infrastructure\RandomApiKeyGenerator;

it('hashes the same key to the same value, so it can be looked up', function () {
    $key = (new RandomApiKeyGenerator)->generate();

    expect($key->hash()->value)->toBe((new PlainApiKey($key->value))->hash()->value)
        ->toMatch('/^[a-f0-9]{64}$/')
        ->not->toContain($key->value);
});

it('generates different keys every time', function () {
    $generator = new RandomApiKeyGenerator;

    expect($generator->generate()->value)->not->toBe($generator->generate()->value);
});

// Un formato inválido es un 401 como cualquier clave inválida, no un 422 que describa el formato.
it('treats a malformed key as an invalid key', function (string $raw) {
    new PlainApiKey($raw);
})->with([
    'empty' => '',
    'without prefix' => str_repeat('a', 64),
    'too short' => 'rl_abc',
    'uppercase' => 'rl_'.str_repeat('A', 64),
])->throws(InvalidApiKeyError::class);
