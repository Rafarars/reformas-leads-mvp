<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class SystemStatus
{
    /**
     * @return array{database: bool, n8n: bool}
     */
    public function check(): array
    {
        return [
            'database' => $this->databaseIsUp(),
            'n8n' => $this->n8nIsUp(),
        ];
    }

    public function databaseIsUp(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function n8nIsUp(): bool
    {
        try {
            // Timeout corto: un n8n caído no debe congelar el panel.
            return Http::timeout(2)
                ->get(config()->string('services.n8n.base_url').'/healthz')
                ->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
