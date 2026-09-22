// Creado por database/seeders/DatabaseSeeder.php, que global-setup ejecuta al empezar.
export const userForWorker = (parallelIndex: number = 0) => ({
    email: `test+${parallelIndex}@example.com`,
    password: 'password',
});

export const TEST_USER = userForWorker(0);

// Sesión guardada por el proyecto setup. Lleva cookies reales: está en .gitignore.
export const storageStateForWorker = (parallelIndex: number = 0): string =>
    `playwright/.auth/user-${parallelIndex}.json`;

export const STORAGE_STATE = storageStateForWorker(0);
