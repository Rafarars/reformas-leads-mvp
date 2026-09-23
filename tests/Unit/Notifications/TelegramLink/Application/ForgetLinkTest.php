<?php

use Reformas\Notifications\TelegramLink\Application\Forget\ForgetLink;
use Reformas\Notifications\TelegramLink\Application\Forget\ForgetLinkRequest;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenHash;
use Reformas\Notifications\TelegramLink\Domain\TelegramChatId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLink;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkId;
use Reformas\Notifications\TelegramLink\Infrastructure\Persistence\InMemory\InMemoryTelegramLinkRepository;

it('removes an existing telegram link for the user', function () {
    $repository = new InMemoryTelegramLinkRepository;
    $now = new DateTimeImmutable('2026-09-23T10:00:00+00:00');
    $link = TelegramLink::request(
        new TelegramLinkId('00000000-0000-7000-8000-000000000001'),
        1,
        new LinkTokenHash(hash('sha256', 'tok')),
        $now->modify('+15 minutes'),
        $now,
    );
    $link->complete(new TelegramChatId('123456789'), $now);
    $repository->save($link);

    $useCase = new ForgetLink($repository);
    $useCase(new ForgetLinkRequest(1));

    expect($repository->searchByUserId(1))->toBeNull();
});

it('is idempotent when user has no link', function () {
    $repository = new InMemoryTelegramLinkRepository;

    $useCase = new ForgetLink($repository);
    $useCase(new ForgetLinkRequest(999));

    expect($repository->searchByUserId(999))->toBeNull();
});
