<?php

namespace App\Support;

use Reformas\Leads\Lead\Application\Receive\LeadReceiverRequest;
use Reformas\Leads\Lead\Domain\RenovationType;

// Leads de demostración. Datos ficticios: por la demo nunca pasan personas reales.
final class DemoLeads
{
    private const SOURCE = 'demo';

    private const NAMES = ['Ana García', 'Luis Martínez', 'Marta López', 'Javier Sánchez', 'Carmen Ruiz', 'Pablo Moreno', 'Lucía Fernández', 'Diego Navarro'];

    private const ZONES = ['Madrid', 'Valencia', 'Sevilla', 'Bilbao', 'Zaragoza', 'Málaga'];

    public static function request(): LeadReceiverRequest
    {
        $types = RenovationType::cases();

        return new LeadReceiverRequest(
            name: self::NAMES[array_rand(self::NAMES)],
            phone: '6'.random_int(10_000_000, 99_999_999),
            // Un email único por lead: la demo no debe chocar con la regla de duplicados.
            email: 'demo.'.bin2hex(random_bytes(6)).'@example.com',
            renovationType: $types[array_rand($types)]->value,
            zone: self::ZONES[array_rand(self::ZONES)],
            budget: random_int(3_000, 60_000),
            description: 'Lead de demostración generado desde el panel de resiliencia.',
            source: self::SOURCE,
        );
    }
}
