<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Idempotente: las pruebas end-to-end lo ejecutan al empezar para garantizar el usuario.
        if (! User::query()->where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        // Usuarios aislados por worker para evitar carreras de sesión y límite de Fortify en pruebas paralelas.
        for ($i = 0; $i < 8; $i++) {
            $email = "test+{$i}@example.com";
            if (! User::query()->where('email', $email)->exists()) {
                User::factory()->create([
                    'name' => "Test User {$i}",
                    'email' => $email,
                ]);
            }
        }
    }
}
