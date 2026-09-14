<?php

namespace Reformas\Integrations\ApiClient\Application\Create;

use Reformas\Integrations\ApiClient\Domain\ApiClient;
use Reformas\Integrations\ApiClient\Domain\ApiClientId;
use Reformas\Integrations\ApiClient\Domain\ApiClientName;
use Reformas\Integrations\ApiClient\Domain\ApiClientRepository;
use Reformas\Integrations\ApiClient\Domain\ApiClientSource;
use Reformas\Integrations\ApiClient\Domain\ApiKeyGenerator;
use Reformas\Shared\Domain\Clock;
use Reformas\Shared\Domain\UuidGenerator;

// Devuelve la clave en vez de void: es el único momento en que existe en claro.
final readonly class ApiClientCreator
{
    public function __construct(
        private ApiClientRepository $repository,
        private ApiKeyGenerator $keyGenerator,
        private UuidGenerator $uuidGenerator,
        private Clock $clock,
    ) {}

    public function __invoke(ApiClientCreatorRequest $request): ApiClientCreatorResponse
    {
        $name = new ApiClientName($request->name);
        $source = new ApiClientSource($request->source);
        $key = $this->keyGenerator->generate();

        $client = ApiClient::register(
            new ApiClientId($this->uuidGenerator->generate()),
            $name,
            $source,
            $key->hash(),
            $this->clock->now(),
        );

        $this->repository->save($client);

        return new ApiClientCreatorResponse($client->toPrimitives()['id'], $key->value);
    }
}
