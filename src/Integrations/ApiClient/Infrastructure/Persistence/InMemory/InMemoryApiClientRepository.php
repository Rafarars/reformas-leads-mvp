<?php

namespace Reformas\Integrations\ApiClient\Infrastructure\Persistence\InMemory;

use Reformas\Integrations\ApiClient\Domain\ApiClient;
use Reformas\Integrations\ApiClient\Domain\ApiClientRepository;
use Reformas\Integrations\ApiClient\Domain\ApiClientSource;
use Reformas\Integrations\ApiClient\Domain\ApiKeyHash;

/**
 * @phpstan-import-type ApiClientPrimitives from ApiClient
 */
final class InMemoryApiClientRepository implements ApiClientRepository
{
    /** @var array<string, ApiClientPrimitives> */
    private array $clients = [];

    public function save(ApiClient $client): void
    {
        $primitives = $client->toPrimitives();
        $this->clients[$primitives['id']] = $primitives;
    }

    public function searchByKeyHash(ApiKeyHash $hash): ?ApiClient
    {
        foreach ($this->clients as $primitives) {
            if ($primitives['key_hash'] === $hash->value) {
                return ApiClient::fromPrimitives($primitives);
            }
        }

        return null;
    }

    public function searchBySource(ApiClientSource $source): ?ApiClient
    {
        foreach ($this->clients as $primitives) {
            if ($primitives['source'] === $source->value) {
                return ApiClient::fromPrimitives($primitives);
            }
        }

        return null;
    }
}
