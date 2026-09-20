<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates the administrator from the environment', function () {
    config(['app.admin.email' => 'admin@empresa.test', 'app.admin.password' => 'una-clave-larga-1234']);

    $this->artisan('app:admin:ensure')->expectsOutputToContain('Administrador listo')->assertSuccessful();

    $admin = User::query()->where('email', 'admin@empresa.test')->firstOrFail();
    expect(Hash::check('una-clave-larga-1234', $admin->password))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull();
});

it('is idempotent and takes the password from the environment every time', function () {
    config(['app.admin.email' => 'admin@empresa.test', 'app.admin.password' => 'primera-clave-1234']);
    $this->artisan('app:admin:ensure')->assertSuccessful();

    config(['app.admin.password' => 'segunda-clave-5678']);
    $this->artisan('app:admin:ensure')->assertSuccessful();

    expect(User::query()->where('email', 'admin@empresa.test')->count())->toBe(1)
        ->and(Hash::check('segunda-clave-5678', User::query()->firstOrFail()->password))->toBeTrue();
});

it('does nothing without both variables, so it never leaves a known password behind', function (?string $email, ?string $password) {
    config(['app.admin.email' => $email, 'app.admin.password' => $password]);

    $this->artisan('app:admin:ensure')->expectsOutputToContain('no se crea ningún administrador')->assertSuccessful();

    expect(User::query()->count())->toBe(0);
})->with([
    'nothing' => [null, null],
    'only the email' => ['admin@empresa.test', null],
    'only the password' => [null, 'una-clave-larga-1234'],
    'empty values' => ['', ''],
]);
