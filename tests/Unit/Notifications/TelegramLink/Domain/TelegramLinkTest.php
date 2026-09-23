<?php

use Reformas\Notifications\TelegramLink\Domain\LinkTokenHash;
use Reformas\Notifications\TelegramLink\Domain\TelegramChatId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLink;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkId;

function createSampleLink(): TelegramLink
{
    $id = new TelegramLinkId('00000000-0000-7000-8000-000000000001');
    $hash = new LinkTokenHash(hash('sha256', 'token-1'));
    $now = new DateTimeImmutable('2026-09-23T10:00:00+00:00');
    $expiresAt = $now->modify('+15 minutes');

    return TelegramLink::request($id, 42, $hash, $expiresAt, $now);
}

it('creates a pending link on request', function () {
    $id = new TelegramLinkId('00000000-0000-7000-8000-000000000001');
    $hash = new LinkTokenHash(hash('sha256', 'token-1'));
    $now = new DateTimeImmutable('2026-09-23T10:00:00+00:00');
    $expiresAt = $now->modify('+15 minutes');

    $link = TelegramLink::request($id, 42, $hash, $expiresAt, $now);

    expect($link->id()->value)->toBe('00000000-0000-7000-8000-000000000001')
        ->and($link->userId())->toBe(42)
        ->and($link->chatId())->toBeNull()
        ->and($link->tokenHash()?->value)->toBe(hash('sha256', 'token-1'))
        ->and($link->tokenExpiresAt())->toEqual($expiresAt)
        ->and($link->tokenUsedAt())->toBeNull()
        ->and($link->linkedAt())->toBeNull()
        ->and($link->createdAt())->toEqual($now)
        ->and($link->updatedAt())->toEqual($now)
        ->and($link->isLinked())->toBeFalse()
        ->and($link->isAlreadyUsed())->toBeFalse()
        ->and($link->isExpired($now))->toBeFalse();
});

it('detects expiration correctly in both directions', function () {
    $link = createSampleLink();
    $now = new DateTimeImmutable('2026-09-23T10:00:00+00:00');

    // Dentro del plazo
    expect($link->isExpired($now->modify('+14 minutes')))->toBeFalse();
    // En el límite exacto
    expect($link->isExpired($now->modify('+15 minutes')))->toBeFalse();
    // Expirado
    expect($link->isExpired($now->modify('+15 minutes +1 second')))->toBeTrue();
});

it('completes the link and records timestamps', function () {
    $link = createSampleLink();
    $completedAt = new DateTimeImmutable('2026-09-23T10:05:00+00:00');
    $chatId = new TelegramChatId('987654321');

    $link->complete($chatId, $completedAt);

    expect($link->isLinked())->toBeTrue()
        ->and($link->chatId()?->value)->toBe('987654321')
        ->and($link->isAlreadyUsed())->toBeTrue()
        ->and($link->tokenUsedAt())->toEqual($completedAt)
        ->and($link->linkedAt())->toEqual($completedAt)
        ->and($link->updatedAt())->toEqual($completedAt);
});

it('renews token and preserves existing chatId until newly completed', function () {
    $link = createSampleLink();
    $firstCompletedAt = new DateTimeImmutable('2026-09-23T10:05:00+00:00');
    $link->complete(new TelegramChatId('987654321'), $firstCompletedAt);

    $renewedAt = new DateTimeImmutable('2026-09-23T11:00:00+00:00');
    $newHash = new LinkTokenHash(hash('sha256', 'token-2'));
    $newExpiresAt = $renewedAt->modify('+15 minutes');

    $link->renewToken($newHash, $newExpiresAt, $renewedAt);

    // El chat anterior sigue activo para no perder avisos mientras no se complete el nuevo
    expect($link->isLinked())->toBeTrue()
        ->and($link->chatId()?->value)->toBe('987654321')
        ->and($link->tokenHash()?->value)->toBe(hash('sha256', 'token-2'))
        ->and($link->tokenExpiresAt())->toEqual($newExpiresAt)
        ->and($link->tokenUsedAt())->toBeNull()
        ->and($link->isAlreadyUsed())->toBeFalse()
        ->and($link->updatedAt())->toEqual($renewedAt);
});

it('serializes to and restores from primitives faithfully', function () {
    $link = createSampleLink();
    $link->complete(new TelegramChatId('987654321'), new DateTimeImmutable('2026-09-23T10:05:00+00:00'));

    $primitives = $link->toPrimitives();
    $restored = TelegramLink::fromPrimitives($primitives);

    expect($restored->toPrimitives())->toBe($primitives);
});
