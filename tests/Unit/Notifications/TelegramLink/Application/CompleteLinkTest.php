<?php

use Reformas\Notifications\TelegramLink\Application\Complete\CompleteLink;
use Reformas\Notifications\TelegramLink\Application\Complete\CompleteLinkRequest;
use Reformas\Notifications\TelegramLink\Domain\ChatAlreadyLinkedError;
use Reformas\Notifications\TelegramLink\Domain\LinkToken;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenAlreadyUsedError;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenExpiredError;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenNotFoundError;
use Reformas\Notifications\TelegramLink\Domain\TelegramChatId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLink;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkId;
use Reformas\Notifications\TelegramLink\Infrastructure\Persistence\InMemory\InMemoryTelegramLinkRepository;
use Tests\Unit\Shared\FixedClock;

const VALID_TOKEN = '1111111111111111111111111111111111111111111111111111111111111111';

function setupPendingLink(
    InMemoryTelegramLinkRepository $repository,
    int $userId = 1,
    string $token = VALID_TOKEN,
    DateTimeImmutable $now = new DateTimeImmutable('2026-09-23T10:00:00+00:00'),
): TelegramLink {
    $tokenVo = new LinkToken($token);
    $link = TelegramLink::request(
        new TelegramLinkId(sprintf('00000000-0000-7000-8000-%012d', $userId)),
        $userId,
        $tokenVo->hash(),
        $now->modify('+15 minutes'),
        $now,
    );
    $repository->save($link);

    return $link;
}

it('completes link successfully when token is valid and unlinked', function () {
    $repository = new InMemoryTelegramLinkRepository;
    $clock = new FixedClock(new DateTimeImmutable('2026-09-23T10:05:00+00:00'));
    setupPendingLink($repository, userId: 1);

    $useCase = new CompleteLink($repository, $clock);
    $response = $useCase(new CompleteLinkRequest(VALID_TOKEN, '123456789'));

    expect($response->userId)->toBe(1)
        ->and($response->chatId)->toBe('123456789')
        ->and($response->linkedAt)->toBe('2026-09-23T10:05:00+00:00');

    $saved = $repository->searchByUserId(1);
    expect($saved?->isLinked())->toBeTrue()
        ->and($saved?->chatId()?->value)->toBe('123456789')
        ->and($saved?->isAlreadyUsed())->toBeTrue();
});

it('rejects an unknown link token', function () {
    $repository = new InMemoryTelegramLinkRepository;
    $clock = new FixedClock;
    $unknownToken = '9999999999999999999999999999999999999999999999999999999999999999';

    $useCase = new CompleteLink($repository, $clock);
    $useCase(new CompleteLinkRequest($unknownToken, '123456789'));
})->throws(LinkTokenNotFoundError::class);

it('rejects an expired link token and allows one within validity window', function () {
    $repository = new InMemoryTelegramLinkRepository;
    $now = new DateTimeImmutable('2026-09-23T10:00:00+00:00');
    $clock = new FixedClock($now);
    setupPendingLink($repository, userId: 1, now: $now);

    $useCase = new CompleteLink($repository, $clock);

    // Guarda en ambos sentidos: pasa dentro del plazo (14 min)
    $clock->advance('+14 minutes');
    $response = $useCase(new CompleteLinkRequest(VALID_TOKEN, '123456789'));
    expect($response->userId)->toBe(1);

    // Segundo token para probar expiración
    $expiredToken = '8888888888888888888888888888888888888888888888888888888888888888';
    setupPendingLink($repository, userId: 2, token: $expiredToken, now: $now);

    // Salta cuando expira (15 min + 1 seg)
    $clock->advance('+2 minutes'); // Ahora estamos en +16 minutos respecto a $now
    $useCase(new CompleteLinkRequest($expiredToken, '987654321'));
})->throws(LinkTokenExpiredError::class);

it('rejects a token that has already been used and allows one that is fresh', function () {
    $repository = new InMemoryTelegramLinkRepository;
    $clock = new FixedClock(new DateTimeImmutable('2026-09-23T10:05:00+00:00'));
    setupPendingLink($repository, userId: 1);

    $useCase = new CompleteLink($repository, $clock);

    // Primer uso: funciona (guarda en sentido fresco)
    $response = $useCase(new CompleteLinkRequest(VALID_TOKEN, '123456789'));
    expect($response->userId)->toBe(1);

    // Segundo uso del mismo token: falla (guarda en sentido ya usado)
    $useCase(new CompleteLinkRequest(VALID_TOKEN, '123456789'));
})->throws(LinkTokenAlreadyUsedError::class);

it('rejects when the telegram chat is already linked to another user account', function () {
    $repository = new InMemoryTelegramLinkRepository;
    $clock = new FixedClock(new DateTimeImmutable('2026-09-23T10:05:00+00:00'));

    // Usuario 1 vincula el chat 123456789
    $link1 = setupPendingLink($repository, userId: 1, token: VALID_TOKEN);
    $link1->complete(new TelegramChatId('123456789'), new DateTimeImmutable('2026-09-23T10:00:00+00:00'));
    $repository->save($link1);

    // Usuario 2 intenta vincular el mismo chat 123456789
    $tokenUser2 = '2222222222222222222222222222222222222222222222222222222222222222';
    setupPendingLink($repository, userId: 2, token: $tokenUser2);

    $useCase = new CompleteLink($repository, $clock);
    $useCase(new CompleteLinkRequest($tokenUser2, '123456789'));
})->throws(ChatAlreadyLinkedError::class);

it('allows the same user to re-link or complete with their own chat id', function () {
    $repository = new InMemoryTelegramLinkRepository;
    $clock = new FixedClock(new DateTimeImmutable('2026-09-23T10:05:00+00:00'));

    // Usuario 1 ya tenía el chat 123456789
    $link1 = setupPendingLink($repository, userId: 1, token: VALID_TOKEN);
    $link1->complete(new TelegramChatId('123456789'), new DateTimeImmutable('2026-09-23T10:00:00+00:00'));
    // Renueva token
    $newToken = '3333333333333333333333333333333333333333333333333333333333333333';
    $tokenVo = new LinkToken($newToken);
    $link1->renewToken($tokenVo->hash(), $clock->now()->modify('+15 minutes'), $clock->now());
    $repository->save($link1);

    $useCase = new CompleteLink($repository, $clock);
    $response = $useCase(new CompleteLinkRequest($newToken, '123456789'));

    expect($response->userId)->toBe(1)
        ->and($response->chatId)->toBe('123456789');
});
