<?php

namespace Reformas\Notifications\TelegramLink\Infrastructure\Persistence\InMemory;

use Reformas\Notifications\TelegramLink\Domain\LinkTokenHash;
use Reformas\Notifications\TelegramLink\Domain\TelegramChatId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLink;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkRepository;

/**
 * @phpstan-import-type TelegramLinkPrimitives from TelegramLink
 */
final class InMemoryTelegramLinkRepository implements TelegramLinkRepository
{
    /** @var array<string, TelegramLinkPrimitives> */
    private array $links = [];

    public function save(TelegramLink $link): void
    {
        $primitives = $link->toPrimitives();
        $this->links[$primitives['id']] = $primitives;
    }

    public function searchByUserId(int $userId): ?TelegramLink
    {
        foreach ($this->links as $primitives) {
            if ($primitives['user_id'] === $userId) {
                return TelegramLink::fromPrimitives($primitives);
            }
        }

        return null;
    }

    public function searchByTokenHash(LinkTokenHash $hash): ?TelegramLink
    {
        foreach ($this->links as $primitives) {
            if ($primitives['token_hash'] === $hash->value) {
                return TelegramLink::fromPrimitives($primitives);
            }
        }

        return null;
    }

    public function searchByChatId(TelegramChatId $chatId): ?TelegramLink
    {
        foreach ($this->links as $primitives) {
            if ($primitives['chat_id'] === $chatId->value) {
                return TelegramLink::fromPrimitives($primitives);
            }
        }

        return null;
    }

    public function delete(TelegramLinkId $id): void
    {
        unset($this->links[$id->value]);
    }

    /**
     * @return list<TelegramLink>
     */
    public function allLinked(): array
    {
        $linked = [];
        foreach ($this->links as $primitives) {
            if ($primitives['chat_id'] !== null) {
                $linked[] = TelegramLink::fromPrimitives($primitives);
            }
        }

        return $linked;
    }
}
