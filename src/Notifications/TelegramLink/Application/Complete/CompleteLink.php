<?php

namespace Reformas\Notifications\TelegramLink\Application\Complete;

use DateTimeInterface;
use Reformas\Notifications\TelegramLink\Domain\ChatAlreadyLinkedError;
use Reformas\Notifications\TelegramLink\Domain\LinkToken;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenAlreadyUsedError;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenExpiredError;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenNotFoundError;
use Reformas\Notifications\TelegramLink\Domain\TelegramChatId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkRepository;
use Reformas\Shared\Domain\Clock;

final readonly class CompleteLink
{
    public function __construct(
        private TelegramLinkRepository $repository,
        private Clock $clock,
    ) {}

    public function __invoke(CompleteLinkRequest $request): CompleteLinkResponse
    {
        $token = new LinkToken($request->token);
        $chatId = new TelegramChatId($request->chatId);
        $now = $this->clock->now();

        $link = $this->repository->searchByTokenHash($token->hash());

        if ($link === null) {
            throw new LinkTokenNotFoundError;
        }

        if ($link->isAlreadyUsed()) {
            throw new LinkTokenAlreadyUsedError;
        }

        if ($link->isExpired($now)) {
            throw new LinkTokenExpiredError;
        }

        // Si este chat_id ya pertenece a otro usuario, se rechaza para evitar avisos duplicados.
        // Si pertenece al mismo usuario (revinculación), es idempotente y se permite.
        $existingWithChat = $this->repository->searchByChatId($chatId);

        if ($existingWithChat !== null && $existingWithChat->userId() !== $link->userId()) {
            throw new ChatAlreadyLinkedError($chatId->value);
        }

        $link->complete($chatId, $now);
        $this->repository->save($link);

        return new CompleteLinkResponse(
            $link->userId(),
            $chatId->value,
            $now->format(DateTimeInterface::ATOM),
        );
    }
}
