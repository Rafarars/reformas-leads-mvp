<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Reformas\Integrations\ApiClient\Application\Authenticate\ApiClientAuthenticator;
use Reformas\Integrations\ApiClient\Application\Authenticate\ApiClientAuthenticatorRequest;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateApiClient
{
    public function __construct(private readonly ApiClientAuthenticator $authenticate) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Sin cabecera llega una cadena vacía, que el dominio rechaza como cualquier clave inválida.
        $client = ($this->authenticate)(new ApiClientAuthenticatorRequest((string) $request->bearerToken()));

        // El origen del lead sale de quien lo envía, nunca del cuerpo de la petición.
        $request->attributes->set('api_client_id', $client->id);
        $request->attributes->set('api_client_source', $client->source);

        return $next($request);
    }
}
