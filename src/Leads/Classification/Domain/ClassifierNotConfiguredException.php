<?php

namespace Reformas\Leads\Classification\Domain;

use RuntimeException;

// No hay modelo configurado: el lead va directo a revisión humana.
final class ClassifierNotConfiguredException extends RuntimeException {}
