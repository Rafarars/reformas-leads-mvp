<?php

namespace Reformas\Notifications\TelegramLink\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use DateTimeInterface;
use Reformas\Notifications\TelegramLink\Domain\LinkTokenHash;
use Reformas\Notifications\TelegramLink\Domain\TelegramChatId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLink;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkId;
use Reformas\Notifications\TelegramLink\Domain\TelegramLinkRepository;

final class EloquentTelegramLinkRepository implements TelegramLinkRepository
{
    public function save(TelegramLink $link): void
    {
        $row = $link->toPrimitives();

        TelegramLinkModel::query()->upsert([$row], ['id'], array_keys(array_diff_key($row, ['id' => true])));
    }

    public function searchByUserId(int $userId): ?TelegramLink
    {
        $model = TelegramLinkModel::query()->where('user_id', $userId)->first();

        return $model === null ? null : $this->toLink($model);
    }

    public function searchByTokenHash(LinkTokenHash $hash): ?TelegramLink
    {
        $model = TelegramLinkModel::query()->where('token_hash', $hash->value)->first();

        return $model === null ? null : $this->toLink($model);
    }

    public function searchByChatId(TelegramChatId $chatId): ?TelegramLink
    {
        $model = TelegramLinkModel::query()->where('chat_id', $chatId->value)->first();

        return $model === null ? null : $this->toLink($model);
    }

    public function delete(TelegramLinkId $id): void
    {
        TelegramLinkModel::query()->where('id', $id->value)->delete();
    }

    /**
     * @return list<TelegramLink>
     */
    public function allLinked(): array
    {
        $models = TelegramLinkModel::query()
            ->whereNotNull('chat_id')
            ->orderBy('created_at')
            ->get();

        $links = [];
        foreach ($models as $model) {
            $links[] = $this->toLink($model);
        }

        return $links;
    }

    private function toLink(TelegramLinkModel $model): TelegramLink
    {
        return TelegramLink::fromPrimitives([
            'id' => (string) $model->getAttribute('id'),
            'user_id' => (int) $model->getAttribute('user_id'),
            'chat_id' => $this->nullableString($model->getAttribute('chat_id')),
            'token_hash' => $this->nullableString($model->getAttribute('token_hash')),
            'token_expires_at' => $this->nullableAtom($model->getAttribute('token_expires_at')),
            'token_used_at' => $this->nullableAtom($model->getAttribute('token_used_at')),
            'linked_at' => $this->nullableAtom($model->getAttribute('linked_at')),
            'created_at' => $this->toAtom($model->getAttribute('created_at')),
            'updated_at' => $this->toAtom($model->getAttribute('updated_at')),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function nullableAtom(mixed $value): ?string
    {
        return $value === null ? null : $this->toAtom($value);
    }

    // Cada motor devuelve las fechas en su formato ("2026-09-14 10:00:00+00" en PostgreSQL):
    // se normalizan para que el dominio reciba siempre lo mismo.
    private function toAtom(mixed $value): string
    {
        return (new DateTimeImmutable((string) $value))->format(DateTimeInterface::ATOM);
    }
}
