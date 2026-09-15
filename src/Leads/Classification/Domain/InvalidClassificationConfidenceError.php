<?php

namespace Reformas\Leads\Classification\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidClassificationConfidenceError extends InvalidArgumentError
{
    public function __construct(float $value)
    {
        parent::__construct(sprintf('<%s> no es una confianza entre 0 y 1', $value));
    }

    public function errorType(): string
    {
        return 'invalid_classification_confidence';
    }

    public function publicMessage(): string
    {
        return 'La confianza debe estar entre 0 y 1.';
    }
}
