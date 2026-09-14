<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable) {
            // 503 y no 200 con error: Docker y el CI deciden por el código de estado.
            // No se expone el mensaje de la excepción: puede revelar host y usuario.
            return response()->json(['status' => 'error', 'database' => 'down'], 503);
        }

        return response()->json(['status' => 'ok', 'database' => 'up']);
    }
}
