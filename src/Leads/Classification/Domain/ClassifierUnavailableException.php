<?php

namespace Reformas\Leads\Classification\Domain;

use RuntimeException;

// Caída pasajera del modelo: la cola lo reintenta.
final class ClassifierUnavailableException extends RuntimeException {}
