<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenHash;
use Reformas\Notifications\TelegramLink\Domain\TelegramChatId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLink;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkRepository;
use Reformas\Notifications\TelegramLink\Infrastructure\Persistence\Eloquent\EloquentTelegramLinkRepository;
use Reformas\Notifications\TelegramLink\Infrastructure\Persistence\InMemory\InMemoryTelegramLinkRepository;

uses(RefreshDatabase::class);

dataset('telegram link repositories', [
    'in memory' => fn () => new InMemoryTelegramLinkRepository,
    'eloquent' => fn () => new EloquentTelegramLinkRepository,
]);

// La clave foránea exige que el usuario exista en la base de datos para Eloquent
function contractUser(int $id = 1): int
{
    User::factory()->create(['id' => $id]);

    return $id;
}

function contractPendingLink(
    int $userId,
    ?string $id = null,
    ?string $hash = null,
): TelegramLink {
    $now = new DateTimeImmutable('2026-09-23T10:00:00+00:00');

    return TelegramLink::request(
        new TelegramLinkId($id ?? sprintf('00000000-0000-7000-8000-%012d', $userId)),
        $userId,
        new LinkTokenHash($hash ?? hash('sha256', 'seed-'.$userId)),
        $now->modify('+15 minutes'),
        $now,
    );
}

it('returns null when searching for an unknown link', function (TelegramLinkRepository $repository) {
    expect($repository->searchByUserId(999))->toBeNull()
        ->and($repository->searchByTokenHash(new LinkTokenHash(str_repeat('f', 64))))->toBeNull()
        ->and($repository->searchByChatId(new TelegramChatId('999999999')))->toBeNull();
})->with('telegram link repositories');

it('saves and retrieves a pending link faithfully by user_id and token_hash', function (TelegramLinkRepository $repository) {
    $userId = contractUser(1);
    $link = contractPendingLink($userId);

    $repository->save($link);

    $byUser = $repository->searchByUserId($userId);
    $byHash = $repository->searchByTokenHash($link->tokenHash());

    expect($byUser?->toPrimitives())->toBe($link->toPrimitives())
        ->and($byHash?->toPrimitives())->toBe($link->toPrimitives())
        ->and($repository->searchByChatId(new TelegramChatId('123456789')))->toBeNull();
})->with('telegram link repositories');

it('saves and retrieves a completed link by chat_id', function (TelegramLinkRepository $repository) {
    $userId = contractUser(2);
    $link = contractPendingLink($userId, '00000000-0000-7000-8000-000000000002');
    $link->complete(new TelegramChatId('987654321'), new DateTimeImmutable('2026-09-23T10:05:00+00:00'));

    $repository->save($link);

    $byChat = $repository->searchByChatId(new TelegramChatId('987654321'));
    expect($byChat?->toPrimitives())->toBe($link->toPrimitives())
        ->and($byChat?->isLinked())->toBeTrue();
})->with('telegram link repositories');

it('updates an existing link when saved again', function (TelegramLinkRepository $repository) {
    $userId = contractUser(3);
    $link = contractPendingLink($userId, '00000000-0000-7000-8000-000000000003');
    $repository->save($link);

    // Se completa el enlace
    $link->complete(new TelegramChatId('555555555'), new DateTimeImmutable('2026-09-23T10:05:00+00:00'));
    $repository->save($link);

    $updated = $repository->searchByUserId($userId);
    expect($updated?->isLinked())->toBeTrue()
        ->and($updated?->chatId()?->value)->toBe('555555555');

    // Se renueva el token
    $newHash = new LinkTokenHash(str_repeat('b', 64));
    $now = new DateTimeImmutable('2026-09-23T11:00:00+00:00');
    $link->renewToken($newHash, $now->modify('+15 minutes'), $now);
    $repository->save($link);

    $renewed = $repository->searchByUserId($userId);
    expect($renewed?->tokenHash()?->value)->toBe(str_repeat('b', 64))
        ->and($renewed?->chatId()?->value)->toBe('555555555');
})->with('telegram link repositories');

it('deletes a link by id', function (TelegramLinkRepository $repository) {
    $userId = contractUser(4);
    $link = contractPendingLink($userId, '00000000-0000-7000-8000-000000000004');
    $repository->save($link);

    $repository->delete($link->id());

    expect($repository->searchByUserId($userId))->toBeNull()
        ->and($repository->searchByTokenHash($link->tokenHash()))->toBeNull();
})->with('telegram link repositories');

it('lists all completed links in order and ignores pending links', function (TelegramLinkRepository $repository) {
    $u1 = contractUser(5);
    $u2 = contractUser(6);
    $u3 = contractUser(7);

    $link1 = contractPendingLink($u1, '00000000-0000-7000-8000-000000000005');
    $link1->complete(new TelegramChatId('111111'), new DateTimeImmutable('2026-09-23T10:05:00+00:00'));
    $repository->save($link1);

    // link2 queda pendiente (sin chat_id)
    $link2 = contractPendingLink($u2, '00000000-0000-7000-8000-000000000006');
    $repository->save($link2);

    $link3 = contractPendingLink($u3, '00000000-0000-7000-8000-000000000007');
    $link3->complete(new TelegramChatId('333333'), new DateTimeImmutable('2026-09-23T10:06:00+00:00'));
    $repository->save($link3);

    $all = $repository->allLinked();

    expect($all)->toHaveCount(2)
        ->and(array_map(static fn (TelegramLink $l): string => $l->chatId()?->value ?? '', $all))->toBe(['111111', '333333']);
})->with('telegram link repositories');
