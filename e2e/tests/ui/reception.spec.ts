import { test } from '../../support/test';
import { DashboardPage } from '../../pages/dashboard-page';
import { ReceptionPage } from '../../pages/reception-page';
import { uniqueLead } from '../../support/leads';

test.beforeEach(async ({ page }) => {
    const dashboard = new DashboardPage(page);

    await dashboard.goto();
    await dashboard.expectAt();
});

test('registers a new lead from the reception module', async ({ page }) => {
    const reception = new ReceptionPage(page);

    await reception.openFromSidebar();
    await reception.register(uniqueLead());

    await reception.expectResult('new');
});

test('recognizes a person who was already registered', async ({ page }) => {
    const reception = new ReceptionPage(page);
    const lead = uniqueLead();

    await reception.openFromSidebar();
    await reception.register(lead);
    await reception.expectResult('new');

    await reception.register({ ...lead, email: `otro.${lead.email}` });

    await reception.expectResult('repeated');
});
