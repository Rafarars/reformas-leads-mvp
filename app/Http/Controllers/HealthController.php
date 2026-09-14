<?php

namespace App\Http\Controllers;

use App\Support\SystemStatus;
use Illuminate\Http\JsonResponse;

class HealthController
{
    // Solo la base: si n8n cae, la app debe seguir aceptando leads (el outbox los retiene).
    public function __invoke(SystemStatus $status): JsonResponse
    {
        if (! $status->databaseIsUp()) {
            // 503 y no 200 con error: Docker y el CI deciden por el código de estado.
            return response()->json(['status' => 'error', 'database' => 'down'], 503);
        }

        return response()->json(['status' => 'ok', 'database' => 'up']);
    }
}
