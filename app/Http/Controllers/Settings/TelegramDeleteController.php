<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Reformas\Notifications\TelegramLink\Application\Forget\ForgetLink;
use Reformas\Notifications\TelegramLink\Application\Forget\ForgetLinkRequest;

final class TelegramDeleteController extends Controller
{
    public function __invoke(Request $request, ForgetLink $forgetLink): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $forgetLink(new ForgetLinkRequest((int) $user->id));

        return back();
    }
}
