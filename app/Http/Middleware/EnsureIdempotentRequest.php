<?php

namespace App\Http\Middleware;

use App\Http\ApiErrorResponse;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class EnsureIdempotentRequest
{
    private const HEADER = 'Idempotency-Key';

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get(self::HEADER);

        // Opcional: sin cabecera, la petición sigue adelante sin garantía de idempotencia.
        if ($key === null) {
            return $next($request);
        }

        if (preg_match('/^[A-Za-z0-9_.:\-]{8,255}$/', $key) !== 1) {
            return ApiErrorResponse::make(
                'invalid_idempotency_key',
                'La Idempotency-Key debe tener entre 8 y 255 caracteres: letras, números, guiones, puntos o dos puntos.',
                422,
            );
        }

        $clientId = $request->attributes->get('api_client_id');

        // Falla en cerrado: sin cliente, las claves de todos los integradores se mezclarían.
        if (! is_string($clientId) || $clientId === '') {
            throw new LogicException('La idempotencia se evaluó antes de autenticar al cliente de la API.');
        }

        $fingerprint = hash('sha256', $request->method().' '.$request->path().' '.$request->getContent());

        // Se reserva la clave antes de procesar: una petición simultánea con la misma clave no la
        // consigue y no llega al caso de uso. insertOrIgnore y no capturar la violación de unicidad,
        // porque en PostgreSQL esa excepción aborta la transacción en curso.
        $reserved = IdempotencyKey::query()->insertOrIgnore([
            'api_client_id' => $clientId,
            'key' => $key,
            'request_fingerprint' => $fingerprint,
            'status' => 'processing',
            'created_at' => now(),
        ]) === 1;

        if (! $reserved) {
            return $this->answerRepeated($clientId, $key, $fingerprint);
        }

        $response = $next($request);
        $record = IdempotencyKey::query()->where('api_client_id', $clientId)->where('key', $key);

        // Un fallo del servidor no se memoriza: el cliente tiene que poder reintentar con la misma clave.
        if ($response->getStatusCode() >= 500) {
            $record->delete();

            return $response;
        }

        $record->update([
            'status' => 'completed',
            'response_status' => $response->getStatusCode(),
            'response_body' => (string) $response->getContent(),
        ]);

        return $response;
    }

    private function answerRepeated(string $clientId, string $key, string $fingerprint): Response
    {
        $stored = IdempotencyKey::query()->where('api_client_id', $clientId)->where('key', $key)->firstOrFail();

        if ($stored->getAttribute('request_fingerprint') !== $fingerprint) {
            return ApiErrorResponse::make('idempotency_key_reused', 'Esta Idempotency-Key ya se usó con otra petición.', 422);
        }

        if ($stored->getAttribute('status') !== 'completed') {
            return ApiErrorResponse::make(
                'idempotency_request_in_progress',
                'Una petición con esta Idempotency-Key todavía se está procesando.',
                409,
            );
        }

        return new Response(
            (string) $stored->getAttribute('response_body'),
            (int) $stored->getAttribute('response_status'),
            ['Content-Type' => 'application/json', 'Idempotent-Replayed' => 'true'],
        );
    }
}
