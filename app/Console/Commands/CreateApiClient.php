<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreator;
use Reformas\Integrations\ApiClient\Application\Create\ApiClientCreatorRequest;
use Reformas\Shared\Domain\DomainError;

#[Signature('app:api-client:create {name : Nombre del integrador} {source : Canal de sus leads, en minúsculas y con guiones}')]
#[Description('Crea un cliente de la API y muestra su clave una sola vez')]
class CreateApiClient extends Command
{
    public function handle(ApiClientCreator $create): int
    {
        try {
            $response = $create(new ApiClientCreatorRequest((string) $this->argument('name'), (string) $this->argument('source')));
        } catch (DomainError $error) {
            $this->error($error->publicMessage());

            return self::FAILURE;
        }

        $this->info("Cliente creado: {$response->id}");
        $this->warn('Guarda esta clave ahora: no se puede volver a mostrar.');
        $this->line($response->apiKey);

        return self::SUCCESS;
    }
}
