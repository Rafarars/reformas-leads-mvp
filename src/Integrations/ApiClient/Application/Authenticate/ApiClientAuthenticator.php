<?php

namespace Reformas\Integrations\ApiClient\Application\Authenticate;

use Reformas\Integrations\ApiClient\Domain\ApiClientRepository;
use Reformas\Integrations\ApiClient\Domain\InvalidApiKeyError;
use Reformas\Integrations\ApiClient\Domain\PlainApiKey;

final readonly class ApiClientAuthenticator
{
    public function __construct(private ApiClientRepository $repository) {}

    public function __invoke(ApiClientAuthenticatorRequest $request): ApiClientAuthenticatorResponse
    {
        $client = $this->repository->searchByKeyHash((new PlainApiKey($request->apiKey))->hash());

        // Clave desconocida y cliente desactivado dan el mismo error: no se confirma que la clave existió.
        if ($client === null || ! $client->isActive()) {
            throw new InvalidApiKeyError;
        }

        $primitives = $client->toPrimitives();

        return new ApiClientAuthenticatorResponse($primitives['id'], $primitives['source']);
    }
}
