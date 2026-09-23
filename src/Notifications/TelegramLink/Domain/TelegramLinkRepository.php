<?php

namespace Reformas\Notifications\TelegramLink\Domain;

interface TelegramLinkRepository
{
    public function save(TelegramLink $link): void;

    public function searchByUserId(int $userId): ?TelegramLink;

    public function searchByTokenHash(LinkTokenHash $hash): ?TelegramLink;

    public function searchByChatId(TelegramChatId $chatId): ?TelegramLink;

    public function delete(TelegramLinkId $id): void;

    /**
     * @return list<TelegramLink>
     */
    public function allLinked(): array;
}
