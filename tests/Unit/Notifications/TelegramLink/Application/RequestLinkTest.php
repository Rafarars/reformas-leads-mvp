<?php

use Reformas\Notifications\TelegramLink\Application\Request\RequestLink;
use Reformas\Notifications\TelegramLink\Application\Request\RequestLinkRequest;
use Reformas\Notifications\TelegramLink\Domain\TelegramChatId;
use Reformas\Notifications\TelegramLink\Infrastructure\Persistence\InMemory\InMemoryTelegramLinkRepository;
use Tests\Unit\Notifications\TelegramLink\FixedLinkTokenGenerator;
use Tests\Unit\Shared\FixedClock;
use Tests\Unit\Shared\SequentialUuidGenerator;

it('generates a new link token for a user without an existing link', function () {
    $repository = new InMemoryTelegramLinkRepository;
    $clock = new FixedClock(new DateTimeImmutable('2026-09-23T10:00:00+00:00'));
    $generator = new FixedLinkTokenGenerator;
    $uuids = new SequentialUuidGenerator;

    $useCase = new RequestLink($repository, $generator, $uuids, $clock);
    $response = $useCase(new RequestLinkRequest(1));

    expect($response->token)->toBe(FixedLinkTokenGenerator::TOKEN)
        ->and($response->expiresAt)->toBe('2026-09-23T10:15:00+00:00');

    $saved = $repository->searchByUserId(1);
    expect($saved)->not->toBeNull()
        ->and($saved?->userId())->toBe(1)
        // El repositorio guarda el hash, nunca el token en claro
        ->and($saved?->tokenHash()?->value)->toBe(hash('sha256', FixedLinkTokenGenerator::TOKEN))
        ->and($saved?->chatId())->toBeNull();
});

it('renews the token when user already has an active linked chat without losing alerts', function () {
    $repository = new InMemoryTelegramLinkRepository;
    $clock = new FixedClock(new DateTimeImmutable('2026-09-23T10:00:00+00:00'));
    $firstToken = '1111111111111111111111111111111111111111111111111111111111111111';
    $firstGenerator = new FixedLinkTokenGenerator($firstToken);
    $uuids = new SequentialUuidGenerator;

    $useCase = new RequestLink($repository, $firstGenerator, $uuids, $clock);
    $useCase(new RequestLinkRequest(1));

    // Completamos el primer enlace
    $link = $repository->searchByUserId(1);
    $link?->complete(new TelegramChatId('987654321'), new DateTimeImmutable('2026-09-23T10:05:00+00:00'));
    $repository->save($link);

    // Más tarde, el usuario pide reconectar o renovar
    $clock->advance('+2 days');
    $secondToken = '2222222222222222222222222222222222222222222222222222222222222222';
    $secondGenerator = new FixedLinkTokenGenerator($secondToken);
    $renewUseCase = new RequestLink($repository, $secondGenerator, $uuids, $clock);

    $response = $renewUseCase(new RequestLinkRequest(1));

    expect($response->token)->toBe($secondToken);

    $updated = $repository->searchByUserId(1);
    // El chat anterior se mantiene intacto para no perder avisos en los 15 minutos de la renovación
    expect($updated?->chatId()?->value)->toBe('987654321')
        ->and($updated?->tokenHash()?->value)->toBe(hash('sha256', $secondToken))
        ->and($updated?->isAlreadyUsed())->toBeFalse();
});
