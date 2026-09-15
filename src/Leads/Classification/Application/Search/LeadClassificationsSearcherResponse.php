<?php

namespace Reformas\Leads\Classification\Application\Search;

final readonly class LeadClassificationsSearcherResponse
{
    /**
     * @param  list<array<string, mixed>>  $classifications
     */
    public function __construct(public array $classifications) {}

    /**
     * @return array{classifications: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['classifications' => $this->classifications];
    }
}
