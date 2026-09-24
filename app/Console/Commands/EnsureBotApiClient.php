<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Reformas\Integrations\ApiClient\Domain\ApiClient;
use Reformas\Integrations\ApiClient\Domain\ApiClientId;
use Reformas\Integrations\ApiClient\Domain\ApiClientName;
use Reformas\Integrations\ApiClient\Domain\ApiClientRepository;
use Reformas\Integrations\ApiClient\Domain\ApiClientSource;
use Reformas\Integrations\ApiClient\Domain\InvalidApiKeyError;
use Reformas\Integrations\ApiClient\Domain\PlainApiKey;
use Reformas\Shared\Domain\Clock;
use Reformas\Shared\Domain\UuidGenerator;

#[Signature('app:api-client:ensure-bot')]
#[Description('Garantiza la existencia del cliente API para el bot de Telegram desde BOT_API_KEY')]
class EnsureBotApiClient extends Command
{
    private const SOURCE = 'telegram-bot';

    private const NAME = 'Telegram Bot';

    public function handle(
        ApiClientRepository $repository,
        UuidGenerator $uuidGenerator,
        Clock $clock,
    ): int {
        $rawKey = config('services.telegram.bot_api_key');

        if (! is_string($rawKey) || trim($rawKey) === '') {
            $this->line('Sin BOT_API_KEY: no se configura el cliente API del bot de Telegram.');

            return self::SUCCESS;
        }

        try {
            $apiKey = new PlainApiKey(trim($rawKey));
        } catch (InvalidApiKeyError) {
            $this->error('BOT_API_KEY no tiene un formato válido (debe ser rl_<64 caracteres hexadecimales>).');

            return self::FAILURE;
        }

        $source = new ApiClientSource(self::SOURCE);
        $client = $repository->searchBySource($source);

        if ($client === null) {
            $client = ApiClient::register(
                new ApiClientId($uuidGenerator->generate()),
                new ApiClientName(self::NAME),
                $source,
                $apiKey->hash(),
                $clock->now(),
            );
            $repository->save($client);

            $this->info('Cliente API del bot de Telegram creado.');

            return self::SUCCESS;
        }

        if ($client->matchesKeyHash($apiKey->hash()) && $client->isActive()) {
            $this->info('Cliente API del bot de Telegram ya configurado.');

            return self::SUCCESS;
        }

        $client->rotateKey($apiKey->hash());
        $repository->save($client);

        $this->info('Cliente API del bot de Telegram actualizado.');

        return self::SUCCESS;
    }
}
