<?php

namespace Reformas\Leads\Classification\Domain;

final readonly class ClassificationConfidence
{
    public function __construct(public float $value)
    {
        if ($value < 0.0 || $value > 1.0) {
            throw new InvalidClassificationConfidenceError($value);
        }
    }

    public function isBelow(float $threshold): bool
    {
        return $this->value < $threshold;
    }
}
