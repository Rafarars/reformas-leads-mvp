<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:admin:ensure')]
#[Description('Crea o actualiza al administrador desde ADMIN_EMAIL y ADMIN_PASSWORD; sin ellas no hace nada')]
class EnsureAdminUser extends Command
{
    public function handle(): int
    {
        $email = config('app.admin.email');
        $password = config('app.admin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            $this->line('Sin ADMIN_EMAIL y ADMIN_PASSWORD: no se crea ningún administrador.');

            return self::SUCCESS;
        }

        // La variable de entorno es la fuente de verdad: cambiarla y reiniciar cambia la contraseña.
        // forceFill: la verificación del correo no es asignable en masa, y sin ella el panel (que exige
        // `verified`) no dejaría entrar al administrador.
        User::query()->firstOrNew(['email' => $email])
            ->forceFill(['name' => 'Administrador', 'password' => $password, 'email_verified_at' => now()])
            ->save();

        $this->info("Administrador listo: {$email}");

        return self::SUCCESS;
    }
}
