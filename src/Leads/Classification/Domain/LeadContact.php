<?php

namespace Reformas\Leads\Classification\Domain;

// A quién avisar o escribir. Viaja en los eventos hacia n8n; nunca hacia la IA.
final readonly class LeadContact
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
    ) {}

    /**
     * @return array{name: string, email: string, phone: string}
     */
    public function toPrimitives(): array
    {
        return ['name' => $this->name, 'email' => $this->email, 'phone' => $this->phone];
    }
}
