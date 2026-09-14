<?php

namespace Tests\Unit\Shared;

use Faker\Factory;
use Faker\Generator;

// Una sola instancia: unique() solo garantiza valores distintos dentro de la misma instancia.
// Con una por llamada, dos emails aleatorios podrían coincidir y la regla de duplicados saltaría.
final class Faker
{
    private static ?Generator $instance = null;

    public static function instance(): Generator
    {
        return self::$instance ??= Factory::create('es_ES');
    }
}
