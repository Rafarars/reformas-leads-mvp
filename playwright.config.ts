import { defineConfig, devices } from '@playwright/test';
import { baseURL } from './e2e/support/environment';

export default defineConfig({
    testDir: './e2e/tests',
    globalSetup: './e2e/global-setup.ts',
    forbidOnly: !!process.env.CI,
    retries: 0,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL,
        // La convención del starter kit: data-test, no data-testid.
        testIdAttribute: 'data-test',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'ui',
            testDir: './e2e/tests/ui',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            // Apaga contenedores: corre al final, para no tumbarle servicios a las demás.
            name: 'resilience',
            testDir: './e2e/tests/resilience',
            dependencies: ['ui'],
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
