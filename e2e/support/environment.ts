import { execFileSync } from 'node:child_process';

export const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8010';

const REQUIRED_SERVICES = ['app', 'db', 'n8n'];

// Las pruebas de resiliencia apagan contenedores: solo corren contra el entorno local.
export function assertDisposableEnvironment(): void {
    const { hostname, origin } = new URL(baseURL);

    if (hostname !== '127.0.0.1' && hostname !== 'localhost') {
        throw new Error(
            `Las pruebas apagan servicios; se niegan a correr contra ${origin}`,
        );
    }

    const running = execFileSync(
        'docker',
        ['compose', 'ps', '--services', '--status', 'running'],
        { encoding: 'utf8' },
    )
        .split('\n')
        .map((service) => service.trim());

    const missing = REQUIRED_SERVICES.filter(
        (service) => !running.includes(service),
    );

    if (missing.length > 0) {
        throw new Error(
            `Servicios detenidos: ${missing.join(', ')}. Ejecuta: docker compose up -d --wait`,
        );
    }
}

export function stopService(service: string): void {
    execFileSync('docker', ['compose', 'stop', service], { stdio: 'ignore' });
}

export function startService(service: string): void {
    execFileSync('docker', ['compose', 'up', '-d', '--wait', service], {
        stdio: 'ignore',
    });
}
