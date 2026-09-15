<?php

namespace Reformas\Leads\Classification\Domain;

// Solo lo que hace falta para clasificar: ni nombre, ni teléfono, ni email salen hacia la IA.
final readonly class LeadToClassify
{
    public function __construct(
        public string $renovationType,
        public ?string $zone,
        public ?int $budget,
        public ?string $description,
    ) {}
}
