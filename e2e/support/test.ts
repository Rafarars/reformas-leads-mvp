import { expect, test as baseTest } from '@playwright/test';
import { storageStateForWorker } from './users';

// IMPORTANTE: Todas las pruebas bajo e2e/tests/ deben importar 'test' y 'expect'
// desde este archivo ('../../support/test') en lugar de '@playwright/test'.
// Este fixture inyecta dinámicamente un storageState aislado por worker
// según su parallelIndex, evitando carreras en sesiones y flashes de Laravel.
export const test = baseTest.extend({
    storageState: async ({ contextOptions }, use, testInfo) => {
        void contextOptions;
        await use(storageStateForWorker(testInfo.parallelIndex));
    },
});

export { expect };
