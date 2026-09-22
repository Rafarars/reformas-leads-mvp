import { test as setup } from '@playwright/test';
import { DashboardPage } from '../../pages/dashboard-page';
import { LoginPage } from '../../pages/login-page';
import {
    STORAGE_STATE,
    storageStateForWorker,
    userForWorker,
} from '../../support/users';

// Sesiones independientes por worker: cada worker inicia sesión con su propio usuario
// (test+0@example.com, test+1@example.com...) en un contexto limpio.
// Claves de Fortify distintas evitan el 429 ("too many attempts"), y cookies de sesión
// distintas evitan que un worker consuma los datos flash de Laravel de otro worker en paralelo.
setup(
    'sign in per worker and keep isolated sessions',
    async ({ browser, page }, testInfo) => {
        const workerCount = Math.max(testInfo.config.workers, 1);

        for (let index = 0; index < workerCount; index++) {
            const user = userForWorker(index);
            const context =
                index === 0 ? page.context() : await browser.newContext();
            const p = index === 0 ? page : await context.newPage();

            const login = new LoginPage(p);
            await login.goto();
            await login.logIn(user.email, user.password);
            await new DashboardPage(p).expectAt();

            const statePath = storageStateForWorker(index);
            await p.context().storageState({ path: statePath });

            if (index === 0) {
                await p.context().storageState({ path: STORAGE_STATE });
            } else {
                await context.close();
            }
        }
    },
);
