<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

// Herramienta de la demo, no parte del producto: controla la API externa simulada de docker/fake-crm.
final readonly class FakeCrmClient
{
    private const TIMEOUT_SECONDS = 2;

    public function __construct(
        private ?string $baseUrl = null,
        private ?string $token = null,
    ) {}

    /**
     * @return array{outage: bool, rejected: int, duplicates: int, deliveries: int}|null
     */
    public function control(): ?array
    {
        if ($this->baseUrl === null || $this->baseUrl === '') {
            return null;
        }

        try {
            $response = $this->request()->get($this->baseUrl.'/control');
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return [
            'outage' => (bool) $response->json('outage'),
            'rejected' => (int) $response->json('rejected'),
            'duplicates' => (int) $response->json('duplicates'),
            'deliveries' => (int) $response->json('deliveries'),
        ];
    }

    public function setOutage(bool $enabled): bool
    {
        if ($this->baseUrl === null || $this->baseUrl === '') {
            return false;
        }

        try {
            return $this->request()
                ->post($this->baseUrl.'/control/outage', ['enabled' => $enabled])
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout(self::TIMEOUT_SECONDS)->acceptJson();

        if ($this->token !== null && $this->token !== '') {
            $request = $request->withToken($this->token);
        }

        return $request;
    }
}
