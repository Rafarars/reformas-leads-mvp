import { TelegramSettingsPage } from '../../pages/telegram-settings-page';
import { test } from '../../support/test';

test.describe('as a guest', () => {
    // Sin la sesión compartida: comprueba que un visitante no entra a ajustes.
    test.use({ storageState: { cookies: [], origins: [] } });

    test('is sent to the login page', async ({ page }) => {
        const telegramPage = new TelegramSettingsPage(page);
        await telegramPage.goto();

        await test.expect(page).toHaveURL(/\/login$/);
    });
});

test('a signed-in user can request a link, view it with expiry, copy it, and disconnect', async ({
    page,
}) => {
    const telegramPage = new TelegramSettingsPage(page);

    await telegramPage.goto();
    await telegramPage.expectAt();

    // 1. Inicialmente no conectado: botón disponible
    await test.expect(telegramPage.connectButton).toBeVisible();

    // 2. Pulsa conectar: genera el enlace con caducidad y botón de copiar
    await telegramPage.requestLink();

    await test.expect(telegramPage.telegramLink).toBeVisible();
    await test
        .expect(telegramPage.telegramLink)
        .toContainText('https://t.me/leads_reformas_mvp_bot?start=');
    await test.expect(telegramPage.linkExpiry).toBeVisible();
    await test.expect(telegramPage.copyLinkButton).toBeVisible();

    // 3. Copia el enlace
    await telegramPage.copyLinkButton.click();

    // 4. Cancela/desconecta y vuelve al estado inicial
    await telegramPage.disconnect();

    await test.expect(telegramPage.connectButton).toBeVisible();
    await test.expect(telegramPage.telegramLink).not.toBeVisible();
});
