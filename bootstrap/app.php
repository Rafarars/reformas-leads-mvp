<?php

use App\Http\ApiErrorResponse;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\AuthenticateApiClient;
use App\Http\Middleware\EnsureIdempotentRequest;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Reformas\Shared\Domain\DomainError;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // Fuera del grupo web a propósito: sin sesión ni cookies que dependan de la base.
        then: function () {
            Route::get('/health', HealthController::class)->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Detrás de un proxy que termina el TLS (Render), la petición llega por http: sin esto
        // Laravel genera las URLs de los assets con http y el navegador las bloquea por contenido
        // mixto, dejando la página en blanco.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'api.client' => AuthenticateApiClient::class,
            'idempotent' => EnsureIdempotentRequest::class,
        ]);

        // Laravel reordena los middlewares por prioridad y ThrottleRequests está en su lista:
        // sin esto corría antes de autenticar y todos los clientes compartían el mismo límite.
        $middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: AuthenticateApiClient::class);
        $middleware->appendToPriorityList(after: ThrottleRequests::class, append: EnsureIdempotentRequest::class);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // La traducción de errores a HTTP de la API ocurre solo aquí. Fuera de /api, el comportamiento por defecto.
        $exceptions->render(fn (DomainError $error, Request $request) => $request->is('api/*')
            ? ApiErrorResponse::fromDomainError($error)
            : null);
        $exceptions->render(fn (ValidationException $exception, Request $request) => $request->is('api/*')
            ? ApiErrorResponse::validation($exception)
            : null);
        $exceptions->render(fn (ThrottleRequestsException $exception, Request $request) => $request->is('api/*')
            ? ApiErrorResponse::tooManyRequests($exception)
            : null);
    })->create();
