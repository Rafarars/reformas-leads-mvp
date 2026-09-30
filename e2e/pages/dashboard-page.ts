import { expect } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

export type Service = 'database' | 'n8n';
export type ServiceState = 'up' | 'down';

export class DashboardPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/dashboard');
    }

    async expectAt(): Promise<void> {
        await expect(this.page).toHaveURL(/\/dashboard$/);
    }

    status(service: Service): Locator {
        return this.page.getByTestId(`status-${service}`);
    }

    async expectService(service: Service, state: ServiceState): Promise<void> {
        await expect(this.status(service)).toHaveAttribute('data-state', state);
    }

    telegramBanner(): Locator {
        return this.page.getByTestId('telegram-banner');
    }

    connectTelegramButton(): Locator {
        return this.page.getByTestId('telegram-banner-button');
    }

    async clickConnectTelegram(): Promise<void> {
        await this.connectTelegramButton().click();
    }

    // El estado se calcula al cargar la página: hay que recargar para ver un cambio.
    // Tras reiniciar un servicio hay unos segundos en que todavía no responde.
    async waitForService(service: Service, state: ServiceState): Promise<void> {
        await expect(async () => {
            await this.page.reload();
            await expect(this.status(service)).toHaveAttribute(
                'data-state',
                state,
                { timeout: 2_000 },
            );
        }).toPass({ timeout: 60_000 });
    }
}
