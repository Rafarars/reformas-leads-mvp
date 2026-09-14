<?php

namespace Reformas\Leads\Lead\Application\Receive;

final readonly class LeadReceiverResponse
{
    public function __construct(
        public string $id,
        public string $status,
        public int $contactCount,
        public bool $repeated,
    ) {}

    /**
     * @return array{id: string, status: string, contact_count: int, repeated: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'contact_count' => $this->contactCount,
            'repeated' => $this->repeated,
        ];
    }
}
