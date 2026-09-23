<?php

use Reformas\Notifications\TelegramLink\Application\List\ListRecipients;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenHash;
use Reformas\Notifications\TelegramLink\Domain\TelegramChatId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLink;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkId;
use Reformas\Notifications\TelegramLink\Infrastructure\Persistence\InMemory\InMemoryTelegramLinkRepository;

it('lists only completed telegram links and provides chatIds helper', function () {
    $repository = new InMemoryTelegramLinkRepository;
    $now = new DateTimeImmutable('2026-09-23T10:00:00+00:00');

    // Enlace 1: completado
    $link1 = TelegramLink::request(
        new TelegramLinkId('00000000-0000-7000-8000-000000000001'),
        1,
        new LinkTokenHash(hash('sha256', 'tok1')),
        $now->modify('+15 minutes'),
        $now,
    );
    $link1->complete(new TelegramChatId('111111'), $now);
    $repository->save($link1);

    // Enlace 2: pendiente (sin chat_id)
    $link2 = TelegramLink::request(
        new TelegramLinkId('00000000-0000-7000-8000-000000000002'),
        2,
        new LinkTokenHash(hash('sha256', 'tok2')),
        $now->modify('+15 minutes'),
        $now,
    );
    $repository->save($link2);

    // Enlace 3: completado
    $link3 = TelegramLink::request(
        new TelegramLinkId('00000000-0000-7000-8000-000000000003'),
        3,
        new LinkTokenHash(hash('sha256', 'tok3')),
        $now->modify('+15 minutes'),
        $now,
    );
    $link3->complete(new TelegramChatId('333333'), $now);
    $repository->save($link3);

    $useCase = new ListRecipients($repository);
    $response = $useCase();

    expect($response->recipients)->toHaveCount(2)
        ->and($response->chatIds())->toBe(['111111', '333333']);
});

it('returns empty list when there are no linked recipients', function () {
    $repository = new InMemoryTelegramLinkRepository;

    $useCase = new ListRecipients($repository);
    $response = $useCase();

    expect($response->recipients)->toBeEmpty()
        ->and($response->chatIds())->toBeEmpty();
});
