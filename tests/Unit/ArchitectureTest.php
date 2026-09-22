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

test('e2e specs do not import test directly from @playwright/test to preserve worker session isolation', function () {
    $e2eTestsDir = dirname(__DIR__, 2).'/e2e/tests';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($e2eTestsDir));
    $found = 0;

    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.spec.ts')) {
            $found++;
            $content = (string) file_get_contents($file->getPathname());
            expect($content)->not->toMatch(
                '/import\s+.*?\btest\b.*?from\s+[\'"]@playwright\/test[\'"]/',
                "El archivo {$file->getPathname()} importa 'test' directamente de '@playwright/test'. Debe importar de '../../support/test' para usar la sesión aislada por worker."
            );
        }
    }

    expect($found)->toBeGreaterThan(0);
});
