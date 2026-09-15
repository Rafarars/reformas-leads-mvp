// Creado por database/seeders/DatabaseSeeder.php, que global-setup ejecuta al empezar.
export const TEST_USER = {
    email: 'test@example.com',
    password: 'password',
} as const;

// Sesión guardada por el proyecto setup. Lleva cookies reales: está en .gitignore.
export const STORAGE_STATE = 'playwright/.auth/user.json';
