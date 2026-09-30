import { DashboardPage } from '../../pages/dashboard-page';
import { TelegramSettingsPage } from '../../pages/telegram-settings-page';
import { test } from '../../support/test';

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

test('an unlinked user sees the telegram banner and navigates to telegram settings where it disappears', async ({
    page,
}) => {
    const dashboard = new DashboardPage(page);
    const telegramPage = new TelegramSettingsPage(page);

    await dashboard.goto();
    await dashboard.expectAt();

    // 1. El aviso aparece en el panel con el texto correspondiente
    await test.expect(dashboard.telegramBanner()).toBeVisible();
    await test
        .expect(dashboard.telegramBanner())
        .toContainText(
            'Conecta tu Telegram para recibir al instante los avisos de leads nuevos.',
        );

    // 2. Pulsa conectar y llega a Ajustes → Telegram
    await dashboard.clickConnectTelegram();
    await telegramPage.expectAt();

    // 3. En la pantalla de Ajustes → Telegram el aviso no aparece
    await test.expect(telegramPage.telegramBanner).not.toBeVisible();
});
