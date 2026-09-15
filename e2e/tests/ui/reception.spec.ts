import { test } from '@playwright/test';
import { DashboardPage } from '../../pages/dashboard-page';
import type { LeadForm } from '../../pages/reception-page';
import { ReceptionPage } from '../../pages/reception-page';

// La base de las pruebas end-to-end no se borra entre corridas: cada ejecución crea su propia
// persona, o la segunda corrida la encontraría ya registrada.
function uniqueLead(): LeadForm {
    const seed = `${Date.now()}${Math.floor(Math.random() * 1000)}`.slice(-8);

    return {
        name: 'Lucía Fernández',
        phone: `6${seed}`,
        email: `lucia.${seed}@example.com`,
        renovationType: 'bathroom',
        zone: 'Valencia',
        budget: 8500,
        description: 'Cambiar la bañera por un plato de ducha',
    };
}

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
