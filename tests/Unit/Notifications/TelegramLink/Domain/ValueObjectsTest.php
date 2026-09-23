<?php

use Reformas\Notifications\TelegramLink\Domain\InvalidLinkTokenError;
use Reformas\Notifications\TelegramLink\Domain\InvalidLinkTokenHashError;
use Reformas\Notifications\TelegramLink\Domain\InvalidTelegramChatIdError;
use Reformas\Notifications\TelegramLink\Domain\LinkToken;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenHash;
use Reformas\Notifications\TelegramLink\Domain\TelegramChatId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkId;
use Reformas\Shared\Domain\InvalidUuidError;

it('accepts valid telegram chat ids', function (string $raw) {
    expect((new TelegramChatId($raw))->value)->toBe($raw);
})->with([
    '123456789',
    '9876543210123456789',
    '-1001234567890',
    '-123456789',
]);

it('rejects malformed telegram chat ids', function (string $raw) {
    new TelegramChatId($raw);
})->with([
    '',
    '   ',
    'abc',
    '123abc456',
    '123 456',
    '123-456',
    '-',
    '--123456',
    '123456789012345678901', // 21 dígitos
])->throws(InvalidTelegramChatIdError::class);

it('accepts valid 64-char lowercase hex link tokens and computes sha256 hash', function () {
    $tokenString = str_repeat('a', 64);
    $token = new LinkToken($tokenString);

    expect($token->value)->toBe($tokenString)
        ->and($token->hash()->value)->toBe(hash('sha256', $tokenString));
});

it('rejects malformed link tokens', function (string $raw) {
    new LinkToken($raw);
})->with([
    '',
    str_repeat('a', 63), // 63 chars
    str_repeat('a', 65), // 65 chars
    str_repeat('g', 64), // non-hex
    str_repeat('A', 64), // uppercase
    ' '.str_repeat('a', 64),
])->throws(InvalidLinkTokenError::class);

it('accepts valid 64-char lowercase hex link token hashes', function () {
    $hashString = hash('sha256', 'some-random-seed');
    $hash = new LinkTokenHash($hashString);

    expect($hash->value)->toBe($hashString);
});

it('rejects malformed link token hashes', function (string $raw) {
    new LinkTokenHash($raw);
})->with([
    '',
    str_repeat('a', 63),
    str_repeat('a', 65),
    str_repeat('z', 64),
    str_repeat('B', 64),
])->throws(InvalidLinkTokenHashError::class);

it('rejects malformed telegram link identifiers', function () {
    new TelegramLinkId('not-a-valid-uuid');
})->throws(InvalidUuidError::class);
