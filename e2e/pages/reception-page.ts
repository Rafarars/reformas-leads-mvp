import { expect } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

export type LeadForm = {
    name: string;
    phone: string;
    email: string;
    renovationType: 'kitchen' | 'bathroom' | 'full_home' | 'partial' | 'other';
    zone?: string;
    budget?: number;
    description?: string;
};

export class ReceptionPage {
    constructor(private readonly page: Page) {}

    async openFromSidebar(): Promise<void> {
        await this.page.getByRole('link', { name: 'Recepción' }).click();
        await expect(this.page).toHaveURL(/\/reception$/);
    }

    async register(lead: LeadForm): Promise<void> {
        await this.page.getByTestId('reception-name').fill(lead.name);
        await this.page.getByTestId('reception-phone').fill(lead.phone);
        await this.page.getByTestId('reception-email').fill(lead.email);
        await this.page.getByTestId('reception-renovation-type').click();
        await this.page
            .getByTestId(`reception-renovation-type-${lead.renovationType}`)
            .click();

        if (lead.zone !== undefined) {
            await this.page.getByTestId('reception-zone').fill(lead.zone);
        }

        if (lead.budget !== undefined) {
            await this.page
                .getByTestId('reception-budget')
                .fill(String(lead.budget));
        }

        if (lead.description !== undefined) {
            await this.page
                .getByTestId('reception-description')
                .fill(lead.description);
        }

        await this.page.getByTestId('reception-submit').click();
    }

    result(): Locator {
        return this.page.getByTestId('reception-result');
    }

    async expectResult(state: 'new' | 'repeated'): Promise<void> {
        await expect(this.result()).toHaveAttribute('data-state', state);
    }
}
