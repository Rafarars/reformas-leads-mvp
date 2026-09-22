import { expect, test } from '../../support/test';

// Vista pública de acceso: no reutilizar la sesión iniciada del setup.
test.use({ storageState: { cookies: [], origins: [] } });

test('shows sign up link when registration is open', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('link', { name: 'Sign up' })).toBeVisible();
});

test('hides sign up link when registration is closed', async ({ page }) => {
    // Simula producción (canRegister: false) interceptando las props de Inertia.
    await page.route('**/login', async (route) => {
        const response = await route.fetch();
        let html = await response.text();
        html = html.replace('"canRegister":true', '"canRegister":false');
        await route.fulfill({ response, body: html });
    });

    await page.goto('/login');
    await expect(page.getByRole('link', { name: 'Sign up' })).not.toBeVisible();
});
