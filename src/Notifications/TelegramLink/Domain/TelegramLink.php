<?php

namespace Reformas\Notifications\TelegramLink\Domain;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * @phpstan-type TelegramLinkPrimitives array{
 *     id: string,
 *     user_id: int,
 *     chat_id: ?string,
 *     token_hash: ?string,
 *     token_expires_at: ?string,
 *     token_used_at: ?string,
 *     linked_at: ?string,
 *     created_at: string,
 *     updated_at: string
 * }
 */
final class TelegramLink
{
    private function __construct(
        private readonly TelegramLinkId $id,
        private readonly int $userId,
        private ?TelegramChatId $chatId,
        private ?LinkTokenHash $tokenHash,
        private ?DateTimeImmutable $tokenExpiresAt,
        private ?DateTimeImmutable $tokenUsedAt,
        private ?DateTimeImmutable $linkedAt,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function request(
        TelegramLinkId $id,
        int $userId,
        LinkTokenHash $tokenHash,
        DateTimeImmutable $tokenExpiresAt,
        DateTimeImmutable $now,
    ): self {
        return new self(
            $id,
            $userId,
            null,
            $tokenHash,
            $tokenExpiresAt,
            null,
            null,
            $now,
            $now,
        );
    }

    // Renueva el token de vinculación. Si el usuario ya tenía un chat_id vinculado,
    // se preserva intacto hasta que el nuevo enlace se complete para no perder avisos.
    public function renewToken(
        LinkTokenHash $tokenHash,
        DateTimeImmutable $tokenExpiresAt,
        DateTimeImmutable $now,
    ): void {
        $this->tokenHash = $tokenHash;
        $this->tokenExpiresAt = $tokenExpiresAt;
        $this->tokenUsedAt = null;
        $this->updatedAt = $now;
    }

    public function complete(TelegramChatId $chatId, DateTimeImmutable $now): void
    {
        $this->chatId = $chatId;
        $this->tokenUsedAt = $now;
        $this->linkedAt = $now;
        $this->updatedAt = $now;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->tokenExpiresAt !== null && $now > $this->tokenExpiresAt;
    }

    public function isAlreadyUsed(): bool
    {
        return $this->tokenUsedAt !== null;
    }

    public function isLinked(): bool
    {
        return $this->chatId !== null;
    }

    public function id(): TelegramLinkId
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function chatId(): ?TelegramChatId
    {
        return $this->chatId;
    }

    public function tokenHash(): ?LinkTokenHash
    {
        return $this->tokenHash;
    }

    public function tokenExpiresAt(): ?DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function tokenUsedAt(): ?DateTimeImmutable
    {
        return $this->tokenUsedAt;
    }

    public function linkedAt(): ?DateTimeImmutable
    {
        return $this->linkedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param  TelegramLinkPrimitives  $primitives
     */
    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            new TelegramLinkId($primitives['id']),
            $primitives['user_id'],
            $primitives['chat_id'] !== null ? new TelegramChatId($primitives['chat_id']) : null,
            $primitives['token_hash'] !== null ? new LinkTokenHash($primitives['token_hash']) : null,
            $primitives['token_expires_at'] !== null ? new DateTimeImmutable($primitives['token_expires_at']) : null,
            $primitives['token_used_at'] !== null ? new DateTimeImmutable($primitives['token_used_at']) : null,
            $primitives['linked_at'] !== null ? new DateTimeImmutable($primitives['linked_at']) : null,
            new DateTimeImmutable($primitives['created_at']),
            new DateTimeImmutable($primitives['updated_at']),
        );
    }

    /**
     * @return TelegramLinkPrimitives
     */
    public function toPrimitives(): array
    {
        return [
            'id' => $this->id->value,
            'user_id' => $this->userId,
            'chat_id' => $this->chatId?->value,
            'token_hash' => $this->tokenHash?->value,
            'token_expires_at' => $this->tokenExpiresAt?->format(DateTimeInterface::ATOM),
            'token_used_at' => $this->tokenUsedAt?->format(DateTimeInterface::ATOM),
            'linked_at' => $this->linkedAt?->format(DateTimeInterface::ATOM),
            'created_at' => $this->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
