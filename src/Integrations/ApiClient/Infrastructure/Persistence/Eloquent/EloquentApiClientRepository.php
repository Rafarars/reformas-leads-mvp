<?php

namespace Reformas\Integrations\ApiClient\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use DateTimeInterface;
use Reformas\Integrations\ApiClient\Domain\ApiClient;
use Reformas\Integrations\ApiClient\Domain\ApiClientRepository;
use Reformas\Integrations\ApiClient\Domain\ApiClientSource;
use Reformas\Integrations\ApiClient\Domain\ApiKeyHash;

final class EloquentApiClientRepository implements ApiClientRepository
{
    public function save(ApiClient $client): void
    {
        $row = $client->toPrimitives();

        ApiClientModel::query()->upsert([$row], ['id'], array_keys(array_diff_key($row, ['id' => true])));
    }

    public function searchByKeyHash(ApiKeyHash $hash): ?ApiClient
    {
        /** @var ApiClientModel|null $model */
        $model = ApiClientModel::query()->where('key_hash', $hash->value)->first();

        return $this->toDomain($model);
    }

    public function searchBySource(ApiClientSource $source): ?ApiClient
    {
        /** @var ApiClientModel|null $model */
        $model = ApiClientModel::query()->where('source', $source->value)->first();

        return $this->toDomain($model);
    }

    private function toDomain(?ApiClientModel $model): ?ApiClient
    {
        if ($model === null) {
            return null;
        }

        return ApiClient::fromPrimitives([
            'id' => (string) $model->getAttribute('id'),
            'name' => (string) $model->getAttribute('name'),
            'source' => (string) $model->getAttribute('source'),
            'key_hash' => (string) $model->getAttribute('key_hash'),
            // SQLite devuelve 1/0 y PostgreSQL true/false.
            'active' => (bool) $model->getAttribute('active'),
            'created_at' => (new DateTimeImmutable((string) $model->getAttribute('created_at')))->format(DateTimeInterface::ATOM),
        ]);
    }
}
