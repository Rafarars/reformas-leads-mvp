<?php

namespace Tests\Unit\Leads\Lead;

use Reformas\Leads\Lead\Application\Receive\LeadReceiverRequest;
use Reformas\Leads\Lead\Domain\RenovationType;
use Tests\Unit\Shared\Faker;

final class LeadReceiverRequestMother
{
    public static function create(
        ?string $name = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $renovationType = null,
        ?string $zone = null,
        ?int $budget = null,
        ?string $description = null,
        ?string $source = null,
    ): LeadReceiverRequest {
        $faker = Faker::instance();

        return new LeadReceiverRequest(
            $name ?? $faker->name(),
            $phone ?? $faker->unique()->numerify('6########'),
            $email ?? $faker->unique()->safeEmail(),
            $renovationType ?? $faker->randomElement(RenovationType::cases())->value,
            $zone ?? $faker->city(),
            $budget ?? $faker->numberBetween(3_000, 90_000),
            $description ?? $faker->sentence(10),
            $source ?? 'web-form',
        );
    }

    // Sin zona, presupuesto ni descripción: para comprobar qué completa un segundo contacto.
    public static function withoutOptionalFields(?string $phone = null, ?string $email = null): LeadReceiverRequest
    {
        $base = self::create(phone: $phone, email: $email);

        return new LeadReceiverRequest(
            $base->name,
            $base->phone,
            $base->email,
            $base->renovationType,
            null,
            null,
            null,
            $base->source,
        );
    }
}
