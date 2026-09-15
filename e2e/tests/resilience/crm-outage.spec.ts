import { expect, test } from '@playwright/test';
import { DashboardPage } from '../../pages/dashboard-page';
import { ReceptionPage } from '../../pages/reception-page';
import { fakeCrm } from '../../support/fake-crm';
import { uniqueLead } from '../../support/leads';

test.describe.configure({ mode: 'serial' });

// Pase lo que pase en la prueba, el destino vuelve a quedar disponible.
test.afterAll(async () => {
    await fakeCrm.setOutage(false);
});

// La pregunta de la entrevista, de punta a punta y con el sistema real: el destino cae, llega un
// lead, el aviso espera sin perderse, y cuando el destino vuelve llega una sola vez.
test('a lead received while the CRM is down reaches it exactly once when it comes back', async ({
    page,
}) => {
    // Backoff y circuito abierto: la entrega puede tardar decenas de segundos tras la recuperación.
    test.setTimeout(300_000);

    await fakeCrm.setOutage(true);
    const rejectedBefore = (await fakeCrm.control()).rejected;

    const lead = uniqueLead();
    const dashboard = new DashboardPage(page);
    const reception = new ReceptionPage(page);

    await dashboard.goto();
    await reception.openFromSidebar();
    await reception.register(lead);
    await reception.expectResult('new');

    // El worker lo intentó y el destino lo rechazó: el aviso está esperando, no perdido.
    await expect
        .poll(async () => (await fakeCrm.control()).rejected, {
            timeout: 90_000,
        })
        .toBeGreaterThan(rejectedBefore);
    expect(await fakeCrm.deliveriesFor(lead.email)).toHaveLength(0);

    await fakeCrm.setOutage(false);

    await expect
        .poll(async () => (await fakeCrm.deliveriesFor(lead.email)).length, {
            timeout: 240_000,
            intervals: [2_000],
        })
        .toBe(1);

    const [delivery] = await fakeCrm.deliveriesFor(lead.email);
    expect(delivery.event).toBe('lead.received');
});
