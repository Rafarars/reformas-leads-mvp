<?php

namespace App\Http\Controllers\Resilience;

use Illuminate\Http\RedirectResponse;
use Reformas\Delivery\Outbox\Application\Replay\OutboxMessageReplayer;
use Reformas\Delivery\Outbox\Application\Replay\OutboxMessageReplayerRequest;
use Reformas\Shared\Domain\DomainError;

final class ResilienceReplayPostController
{
    public function __invoke(string $message, OutboxMessageReplayer $replay): RedirectResponse
    {
        try {
            $replay(new OutboxMessageReplayerRequest($message));
        } catch (DomainError $error) {
            return back()->withErrors(['replay' => $error->publicMessage()]);
        }

        return back();
    }
}
