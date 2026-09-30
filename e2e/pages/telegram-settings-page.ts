import { expect } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

export class TelegramSettingsPage {
    readonly connectButton: Locator;
    readonly telegramLink: Locator;
    readonly copyLinkButton: Locator;
    readonly linkExpiry: Locator;
    readonly connectedSince: Locator;
    readonly disconnectButton: Locator;
    readonly telegramBanner: Locator;

    constructor(private readonly page: Page) {
        this.connectButton = this.page.getByTestId('connect-button');
        this.telegramLink = this.page.getByTestId('telegram-link');
        this.copyLinkButton = this.page.getByTestId('copy-link-button');
        this.linkExpiry = this.page.getByTestId('link-expiry');
        this.connectedSince = this.page.getByTestId('connected-since');
        this.disconnectButton = this.page.getByTestId('disconnect-button');
        this.telegramBanner = this.page.getByTestId('telegram-banner');
    }

    async goto(): Promise<void> {
        await this.page.goto('/settings/telegram');
    }

    async expectAt(): Promise<void> {
        await expect(this.page).toHaveURL(/\/settings\/telegram$/);
    }

    async requestLink(): Promise<void> {
        await this.connectButton.click();
    }

    async disconnect(): Promise<void> {
        await this.disconnectButton.click();
    }
}
