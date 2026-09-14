<?php

namespace Reformas\Leads\Lead\Application\Receive;

final readonly class LeadReceiverRequest
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $email,
        public string $renovationType,
        public ?string $zone,
        public ?int $budget,
        public ?string $description,
        public string $source,
    ) {}
}
