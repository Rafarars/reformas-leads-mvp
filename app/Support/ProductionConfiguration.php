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

        $n8nHost = parse_url(config()->string('services.n8n.base_url', ''), PHP_URL_HOST);

        if (! is_string($n8nHost) || $this->isLoopback($n8nHost)) {
            $problems[] = 'N8N_BASE_URL falta o apunta a localhost.';
        }

        return $problems;
    }

    private function isLoopback(string $host): bool
    {
        return in_array(strtolower(trim($host, '[]')), self::LOOPBACK_HOSTS, true);
    }
}
