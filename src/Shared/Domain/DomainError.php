<?php

namespace Reformas\Shared\Domain;

use DomainException;

// Una regla de negocio dijo que no. getMessage() puede llevar datos para los registros;
// publicMessage() es lo único que sale por HTTP.
abstract class DomainError extends DomainException
{
    // Identificador estable y legible: el cliente distingue casos sin parsear el texto.
    abstract public function errorType(): string;

    abstract public function publicMessage(): string;
}
