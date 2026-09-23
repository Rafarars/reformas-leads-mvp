<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use DateTimeInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkRepository;
use Reformas\Shared\Domain\Clock;

final class TelegramGetController extends Controller
{
    public function __invoke(
        Request $request,
        TelegramLinkRepository $repository,
        Clock $clock,
    ): Response {
        $user = $request->user();
        $link = $user !== null ? $repository->searchByUserId((int) $user->id) : null;
        $now = $clock->now();

        $connected = $link !== null && $link->isLinked();
        $pending = $link !== null && ! $link->isLinked() && ! $link->isExpired($now) && ! $link->isAlreadyUsed();

        /** @var array{token: string, expires_at: string}|null $flashLink */
        $flashLink = $request->session()->get('telegram_flash_link');

        $botUsername = (string) config('services.telegram.bot_username');

        return Inertia::render('settings/telegram', [
            'telegram' => [
                'connected' => $connected,
                'pending' => $pending,
                'chat_id' => $connected ? $link->chatId()?->value : null,
                'linked_at' => $connected ? $link->linkedAt()?->format(DateTimeInterface::ATOM) : null,
                'fresh_link' => ($flashLink !== null && $botUsername !== '') ? [
                    'token' => $flashLink['token'],
                    'url' => "https://t.me/{$botUsername}?start={$flashLink['token']}",
                    'expires_at' => $flashLink['expires_at'],
                ] : null,
                'bot_username' => $botUsername !== '' ? $botUsername : null,
            ],
        ]);
    }
}
