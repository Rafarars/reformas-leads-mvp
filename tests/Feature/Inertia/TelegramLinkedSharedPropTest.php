<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenHash;
use Reformas\Notifications\TelegramLink\Domain\TelegramChatId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLink;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake(['*/healthz' => Http::response(['status' => 'ok'])]);
});

it('shares null for telegramLinked when user is a guest', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('telegramLinked', null));
});

it('shares false for telegramLinked when authenticated user has no linked telegram', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('telegramLinked', false));
});

it('shares true for telegramLinked when authenticated user has a completed telegram link', function () {
    $user = User::factory()->create();

    $link = TelegramLink::request(
        new TelegramLinkId('00000000-0000-7000-8000-000000000001'),
        (int) $user->id,
        new LinkTokenHash(hash('sha256', 'token-test')),
        new DateTimeImmutable('2026-09-30T12:15:00+00:00'),
        new DateTimeImmutable('2026-09-30T12:00:00+00:00'),
    );
    $link->complete(new TelegramChatId('123456789'), new DateTimeImmutable('2026-09-30T12:05:00+00:00'));

    app(TelegramLinkRepository::class)->save($link);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('telegramLinked', true));
});

it('evaluates telegramLinked lazily so partial reloads not asking for it never query the repository', function () {
    $user = User::factory()->create();
    $version = app(HandleInertiaRequests::class)->version(request());

    // En una recarga parcial que no solicita telegramLinked, la closure no debe ejecutarse.
    $repository = Mockery::mock(TelegramLinkRepository::class);
    $repository->shouldNotReceive('searchByUserId');
    $this->app->instance(TelegramLinkRepository::class, $repository);

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'dashboard',
        'X-Inertia-Partial-Data' => 'name',
    ];
    if ($version !== null) {
        $headers['X-Inertia-Version'] = $version;
    }

    $this->actingAs($user)->get(route('dashboard'), $headers)->assertOk();

    // Contraste: cuando la recarga parcial sí pide telegramLinked, la closure se evalúa y consulta el repositorio.
    $activeRepository = Mockery::mock(TelegramLinkRepository::class);
    $activeRepository->shouldReceive('searchByUserId')
        ->once()
        ->with((int) $user->id)
        ->andReturn(null);
    $this->app->instance(TelegramLinkRepository::class, $activeRepository);

    $headers['X-Inertia-Partial-Data'] = 'telegramLinked';

    $this->actingAs($user)->get(route('dashboard'), $headers)->assertOk();
});
