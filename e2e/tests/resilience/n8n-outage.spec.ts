import { test } from '@playwright/test';
import { DashboardPage } from '../../pages/dashboard-page';
import { startService, stopService } from '../../support/environment';

test.describe.configure({ mode: 'serial' });

// Pase lo que pase en la prueba, n8n vuelve a quedar arriba.
test.afterAll(() => {
    startService('n8n');
});

test('the panel detects n8n going down and coming back', async ({ page }) => {
    // Detener y levantar un contenedor con --wait tarda más que el límite por defecto.
    test.setTimeout(180_000);

    const dashboard = new DashboardPage(page);

    await dashboard.goto();
    await dashboard.expectAt();
    await dashboard.expectService('n8n', 'up');

    stopService('n8n');

    await dashboard.waitForService('n8n', 'down');
    // Que caiga n8n no arrastra a la base: el panel sigue funcionando.
    await dashboard.expectService('database', 'up');

    startService('n8n');

    await dashboard.waitForService('n8n', 'up');
});
