<?php

namespace Reformas\Notifications\TelegramLink\Application\Forget;

use Reformas\Notifications\TelegramLink\Domain\TelegramLinkRepository;

final readonly class ForgetLink
{
    public function __construct(private TelegramLinkRepository $repository) {}

    public function __invoke(ForgetLinkRequest $request): void
    {
        $link = $this->repository->searchByUserId($request->userId);

        if ($link !== null) {
            $this->repository->delete($link->id());
        }
    }
}
