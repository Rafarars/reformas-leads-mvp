<?php

namespace Reformas\Leads\Classification\Domain;

use Reformas\Shared\Domain\InvalidArgumentError;

final class InvalidWorkCategoryError extends InvalidArgumentError
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('<%s> no es una categoría de trabajo válida', $value));
    }

    public function errorType(): string
    {
        return 'invalid_work_category';
    }

    public function publicMessage(): string
    {
        return 'La categoría no es válida.';
    }
}
