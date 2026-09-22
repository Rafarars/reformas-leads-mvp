import { test } from '../../support/test';
import { DashboardPage } from '../../pages/dashboard-page';

test.describe('as a guest', () => {
    // Sin la sesión compartida: comprueba que un visitante no entra.
    test.use({ storageState: { cookies: [], origins: [] } });

    test('is sent to the login page', async ({ page }) => {
        await new DashboardPage(page).goto();

        await test.expect(page).toHaveURL(/\/login$/);
    });
});

test('a signed-in user sees every service up', async ({ page }) => {
    const dashboard = new DashboardPage(page);

    await dashboard.goto();

    await dashboard.expectAt();
    await dashboard.expectService('database', 'up');
    await dashboard.expectService('n8n', 'up');
});
