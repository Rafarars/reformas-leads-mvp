import { execFileSync } from 'node:child_process';
import { existsSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import {
    assertDisposableEnvironment,
    startService,
} from './support/environment';

function newestModification(directory: string): number {
    return readdirSync(directory, { recursive: true, withFileTypes: true })
        .filter((entry) => entry.isFile())
        .map((entry) => statSync(join(entry.parentPath, entry.name)).mtimeMs)
        .reduce((newest, current) => Math.max(newest, current), 0);
}

// Sin Vite corriendo, la app sirve public/build: si es más viejo que el código,
// las pruebas verificarían una interfaz que ya no existe, sin avisar.
function assertAssetsAreFresh(): void {
    if (existsSync('public/hot')) {
        return;
    }

    const manifest = 'public/build/manifest.json';

    if (
        !existsSync(manifest) ||
        statSync(manifest).mtimeMs < newestModification('resources/js')
    ) {
        throw new Error(
            'Los assets compilados son más viejos que resources/js. Ejecuta: npm run build',
        );
    }
}

export default function globalSetup(): void {
    assertDisposableEnvironment();
    assertAssetsAreFresh();

    // Se garantiza el punto de partida en vez de confiar en cómo quedó la corrida anterior.
    startService('n8n');
    execFileSync(
        'docker',
        [
            'compose',
            'exec',
            '-T',
            'app',
            'php',
            'artisan',
            'db:seed',
            '--force',
        ],
        { stdio: 'ignore' },
    );
}
