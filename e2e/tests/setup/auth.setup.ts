import { test as setup } from '@playwright/test';
import { DashboardPage } from '../../pages/dashboard-page';
import { LoginPage } from '../../pages/login-page';
import { STORAGE_STATE, TEST_USER } from '../../support/users';

// Una sola sesión por corrida: con un login por prueba, dos corridas seguidas superaban el límite
// de Fortify (5 intentos por minuto) y la segunda recibía 429. El flujo de login se sigue
// recorriendo aquí, a través de la interfaz.
setup('sign in once and keep the session', async ({ page }) => {
    const login = new LoginPage(page);

    await login.goto();
    await login.logIn(TEST_USER.email, TEST_USER.password);
    await new DashboardPage(page).expectAt();

    await page.context().storageState({ path: STORAGE_STATE });
});
