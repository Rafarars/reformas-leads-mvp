<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Reformas\Notifications\TelegramLink\Application\Request\RequestLink;
use Reformas\Notifications\TelegramLink\Application\Request\RequestLinkRequest;

final class TelegramLinkPostController extends Controller
{
    public function __invoke(Request $request, RequestLink $requestLink): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $response = $requestLink(new RequestLinkRequest((int) $user->id));

        // El token en claro solo viaja una vez en el flash de la redirección.
        // No se almacena en la sesión persistente para evitar escribir el secreto en la base de datos.
        return back()->with('telegram_flash_link', [
            'token' => $response->token,
            'expires_at' => $response->expiresAt,
        ]);
    }
}
