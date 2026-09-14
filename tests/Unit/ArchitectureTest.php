<?php

// Regla de oro: el dominio y la aplicación solo usan código propio.
// Infrastructure sí puede usar Laravel; por eso la regla se aplica por capa y no a todo src/.
const FRAMEWORK = ['Illuminate', 'Laravel', 'Inertia', 'Carbon'];

arch('domain does not depend on the framework')
    ->expect('Reformas\*\*\Domain')
    ->not->toUse(FRAMEWORK);

arch('application does not depend on the framework')
    ->expect('Reformas\*\*\Application')
    ->not->toUse(FRAMEWORK);

arch('domain does not depend on application or infrastructure')
    ->expect('Reformas\*\*\Domain')
    ->not->toUse(['Reformas\*\*\Application', 'Reformas\*\*\Infrastructure']);

arch('application does not depend on infrastructure')
    ->expect('Reformas\*\*\Application')
    ->not->toUse('Reformas\*\*\Infrastructure');
