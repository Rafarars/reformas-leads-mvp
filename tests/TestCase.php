<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ninguna prueba depende de un servicio real: una llamada HTTP sin simular falla al instante.
        Http::preventStrayRequests();

        // Las pruebas de backend no dependen del frontend compilado: sin esto, un clon
        // sin public/build responde 500 en toda vista que use @vite.
        $this->withoutVite();

        // La cola se simula: una prueba de la API no dispara entregas reales. Las pruebas de la
        // entrega ejecutan el trabajo a propósito.
        Queue::fake();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
