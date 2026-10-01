<?php

namespace App\Support;

class ProductionConfiguration
{
    private const LOOPBACK_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    /**
     * Problemas que impiden arrancar en producción. Fuera de producción no exige nada.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        if (! app()->isProduction()) {
            return [];
        }

        $problems = [];

        if (blank(config('app.key'))) {
            $problems[] = 'APP_KEY está vacía.';
        }

        if (config('app.debug') === true) {
            $problems[] = 'APP_DEBUG está activo: expondría trazas y variables a cualquier visitante.';
        }

        // El accidente clásico: una variable de desarrollo que llega a producción.
        if ($this->isLoopback(config()->string('database.connections.pgsql.host', ''))) {
            $problems[] = 'DB_HOST apunta a localhost.';
        }

        $n8nHost = $this->hostOf(config('services.n8n.base_url'));

        if (! is_string($n8nHost) || $this->isLoopback($n8nHost)) {
            $problems[] = 'N8N_BASE_URL falta o apunta a localhost.';
        }

        foreach (['crm' => 'DELIVERY_CRM_URL', 'team' => 'DELIVERY_TEAM_URL', 'customer' => 'DELIVERY_CUSTOMER_URL'] as $destination => $variable) {
            $host = $this->hostOf(config("services.delivery.destinations.{$destination}.url"));

            if (! is_string($host) || $this->isLoopback($host)) {
                $problems[] = "{$variable} falta o apunta a localhost.";
            }
        }

        $fakeCrmHost = $this->hostOf(config('services.fake_crm.url'));

        if ($fakeCrmHost !== null) {
            if ($this->isLoopback($fakeCrmHost)) {
                $problems[] = 'FAKE_CRM_URL apunta a localhost.';
            }

            if (blank(config('services.fake_crm.token'))) {
                $problems[] = 'FAKE_CRM_URL está definida pero falta FAKE_CRM_TOKEN.';
            }
        }

        return $problems;
    }

    // Una variable sin definir llega como null, no como cadena vacía: config()->string() lanzaría una
    // excepción en vez de informar el problema.
    private function hostOf(mixed $url): ?string
    {
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

        return is_string($host) ? $host : null;
    }

    private function isLoopback(string $host): bool
    {
        return in_array(strtolower(trim($host, '[]')), self::LOOPBACK_HOSTS, true);
    }
}
