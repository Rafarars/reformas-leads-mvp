import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

export type OutboxCounter = 'pending' | 'delivered' | 'dead';

export class ResiliencePage {
    constructor(private readonly page: Page) {}

    async openFromSidebar(): Promise<void> {
        await this.page.getByRole('link', { name: 'Resiliencia' }).click();
        await expect(this.page).toHaveURL(/\/resilience$/);
    }

    async count(counter: OutboxCounter): Promise<number> {
        return Number(
            await this.page
                .getByTestId(`resilience-count-${counter}`)
                .getAttribute('data-value'),
        );
    }

    async turnOutageOn(): Promise<void> {
        // Con el CRM inalcanzable los botones están desactivados: fallar aquí con un motivo claro,
        // no esperar minutos a que un botón deshabilitado acepte el clic.
        await this.expectCrm('up');
        await this.page.getByTestId('resilience-outage-on').click();
        await this.expectCrm('down');
    }

    async turnOutageOff(): Promise<void> {
        await this.page.getByTestId('resilience-outage-off').click();
        await this.expectCrm('up');
    }

    async expectCrm(state: 'up' | 'down'): Promise<void> {
        await expect(this.page.getByTestId('resilience-crm')).toHaveAttribute(
            'data-state',
            state,
        );
    }

    async injectDemoLeads(): Promise<void> {
        await this.page.getByTestId('resilience-inject').click();
    }
}
