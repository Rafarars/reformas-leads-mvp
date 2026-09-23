<?php

namespace App\Http\Controllers\Api\V1\Telegram;

use App\Http\Requests\Api\V1\Telegram\LinkTelegramRequest;
use Illuminate\Http\JsonResponse;
use Reformas\Notifications\TelegramLink\Application\Complete\CompleteLink;
use Reformas\Notifications\TelegramLink\Application\Complete\CompleteLinkRequest;

final class TelegramLinkPostController
{
    public function __invoke(LinkTelegramRequest $request, CompleteLink $completeLink): JsonResponse
    {
        $response = $completeLink(new CompleteLinkRequest(
            token: (string) $request->validated('token'),
            chatId: (string) $request->validated('chat_id'),
        ));

        return new JsonResponse([
            'user_id' => $response->userId,
            'chat_id' => $response->chatId,
            'linked_at' => $response->linkedAt,
        ], 200);
    }
}
