import { test } from '@playwright/test';
import { DashboardPage } from '../../pages/dashboard-page';
import { LoginPage } from '../../pages/login-page';
import { TEST_USER } from '../../support/users';

test('guests are sent to the login page', async ({ page }) => {
    const dashboard = new DashboardPage(page);

    await dashboard.goto();

    await test.expect(page).toHaveURL(/\/login$/);
});

test('a signed-in user sees every service up', async ({ page }) => {
    const login = new LoginPage(page);
    const dashboard = new DashboardPage(page);

    await login.goto();
    await login.logIn(TEST_USER.email, TEST_USER.password);

    await dashboard.expectAt();
    await dashboard.expectService('database', 'up');
    await dashboard.expectService('n8n', 'up');
});
