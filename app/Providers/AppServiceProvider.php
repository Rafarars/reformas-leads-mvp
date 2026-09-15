<?php

namespace App\Providers;

use App\Services\FakeCrmClient;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FakeCrmClient::class, fn (): FakeCrmClient => new FakeCrmClient(config()->string('services.fake_crm.url')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        // Por cliente de la API: un integrador roto no deja sin servicio a los demás.
        RateLimiter::for('api-clients', function (Request $request): Limit {
            $clientId = $request->attributes->get('api_client_id');

            // Falla en cerrado: sin cliente, la clave sería vacía y todos compartirían el mismo límite.
            if (! is_string($clientId) || $clientId === '') {
                throw new LogicException('El límite por cliente se evaluó antes de autenticar al cliente de la API.');
            }

            return Limit::perMinute(config()->integer('services.api.requests_per_minute'))->by($clientId);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
