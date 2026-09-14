<?php

namespace Reformas\Integrations\ApiClient\Domain;

interface ApiClientRepository
{
    public function save(ApiClient $client): void;

    public function searchByKeyHash(ApiKeyHash $hash): ?ApiClient;
}
