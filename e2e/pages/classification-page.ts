import { expect } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

export class ClassificationPage {
    constructor(private readonly page: Page) {}

    async openFromSidebar(): Promise<void> {
        await this.page.getByRole('link', { name: 'Clasificación' }).click();
        await expect(this.page).toHaveURL(/\/classification$/);
    }

    itemFor(description: string): Locator {
        return this.page
            .getByTestId('classification-item')
            .filter({ hasText: description });
    }

    async review(
        description: string,
        category: string,
        urgency: 'low' | 'medium' | 'high',
    ): Promise<void> {
        const item = this.itemFor(description);
        const leadId = await item.getAttribute('data-lead');
        const prefix = `classification-review-${leadId}`;

        await this.page.getByTestId(`${prefix}-category`).click();
        await this.page.getByTestId(`${prefix}-category-${category}`).click();
        await this.page.getByTestId(`${prefix}-urgency`).click();
        await this.page.getByTestId(`${prefix}-urgency-${urgency}`).click();
        await this.page.getByTestId(`${prefix}-submit`).click();
    }
}
