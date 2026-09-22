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
        await this.centre(item);
        const leadId = await item.getAttribute('data-lead');
        const prefix = `classification-review-${leadId}`;

        const categoryTrigger = this.page.getByTestId(`${prefix}-category`);
        const categoryOption = this.page.getByTestId(
            `${prefix}-category-${category}`,
        );
        await this.centre(categoryTrigger);
        await categoryTrigger.click();
        await categoryOption.click();
        await expect(categoryOption).not.toBeVisible();

        const urgencyTrigger = this.page.getByTestId(`${prefix}-urgency`);
        const urgencyOption = this.page.getByTestId(
            `${prefix}-urgency-${urgency}`,
        );
        await this.centre(urgencyTrigger);
        await urgencyTrigger.click();
        await urgencyOption.click();
        await expect(urgencyOption).not.toBeVisible();

        const submitButton = this.page.getByTestId(`${prefix}-submit`);
        await this.centre(submitButton);
        await submitButton.click();
    }

    async attach(
        description: string,
        kind: 'photo' | 'plan',
        file: { name: string; mimeType: string; buffer: Buffer },
    ): Promise<void> {
        const leadId =
            await this.itemFor(description).getAttribute('data-lead');
        const prefix = `classification-attachments-${leadId}`;

        const kindTrigger = this.page.getByTestId(`${prefix}-kind`);
        const kindOption = this.page.getByTestId(`${prefix}-kind-${kind}`);
        await this.centre(kindTrigger);
        await kindTrigger.click();
        await kindOption.click();
        await expect(kindOption).not.toBeVisible();

        // Subir al elegir el archivo: no hay botón de confirmar.
        await this.page.getByTestId(`${prefix}-file-input`).setInputFiles(file);
    }

    attachments(description: string): Locator {
        return this.itemFor(description).getByTestId(
            /classification-attachments-.*-link/,
        );
    }

    // El menú del Select de Radix se abre en un portal, y si el trigger queda pegado al borde
    // inferior, las opciones caen fuera de la ventana.
    private async centre(locator: Locator): Promise<void> {
        await locator.evaluate((el) => el.scrollIntoView({ block: 'center' }));
    }
}
