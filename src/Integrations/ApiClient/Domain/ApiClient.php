<?php

namespace Reformas\Integrations\ApiClient\Domain;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * @phpstan-type ApiClientPrimitives array{
 *     id: string,
 *     name: string,
 *     source: string,
 *     key_hash: string,
 *     active: bool,
 *     created_at: string
 * }
 */
final class ApiClient
{
    private function __construct(
        private readonly ApiClientId $id,
        private readonly ApiClientName $name,
        private readonly ApiClientSource $source,
        private readonly ApiKeyHash $keyHash,
        private bool $active,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function register(
        ApiClientId $id,
        ApiClientName $name,
        ApiClientSource $source,
        ApiKeyHash $keyHash,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $name, $source, $keyHash, true, $now);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    /**
     * @param  ApiClientPrimitives  $primitives
     */
    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            new ApiClientId($primitives['id']),
            new ApiClientName($primitives['name']),
            new ApiClientSource($primitives['source']),
            new ApiKeyHash($primitives['key_hash']),
            $primitives['active'],
            new DateTimeImmutable($primitives['created_at']),
        );
    }

    /**
     * @return ApiClientPrimitives
     */
    public function toPrimitives(): array
    {
        return [
            'id' => $this->id->value,
            'name' => $this->name->value,
            'source' => $this->source->value,
            'key_hash' => $this->keyHash->value,
            'active' => $this->active,
            'created_at' => $this->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
