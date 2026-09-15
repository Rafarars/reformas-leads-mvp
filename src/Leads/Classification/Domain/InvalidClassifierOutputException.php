<?php

namespace Reformas\Leads\Classification\Domain;

use RuntimeException;

// El modelo respondió fuera del contrato: no se adivina, se revisa a mano.
final class InvalidClassifierOutputException extends RuntimeException {}
