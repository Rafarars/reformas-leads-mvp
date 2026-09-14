import type { Page } from '@playwright/test';

export class LoginPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/login');
    }

    async logIn(email: string, password: string): Promise<void> {
        await this.page.getByLabel('Email address').fill(email);
        await this.page.getByLabel('Password', { exact: true }).fill(password);
        await this.page.getByTestId('login-button').click();
    }
}
