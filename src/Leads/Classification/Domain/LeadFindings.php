<?php

namespace Reformas\Leads\Classification\Domain;

// Hallazgos cortos y pocos: una lista larga no la lee nadie antes de llamar al cliente.
final readonly class LeadFindings
{
    private const MAX_ITEMS = 5;

    private const MAX_LENGTH = 200;

    /** @var list<string> */
    public array $items;

    /**
     * @param  list<string>  $items
     */
    public function __construct(array $items)
    {
        if (count($items) > self::MAX_ITEMS) {
            throw new InvalidLeadFindingsError(sprintf('%d elementos', count($items)));
        }

        $normalized = [];

        foreach ($items as $item) {
            $value = trim((string) preg_replace('/\s+/u', ' ', $item));

            if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
                throw new InvalidLeadFindingsError($item);
            }

            $normalized[] = $value;
        }

        $this->items = $normalized;
    }
}
