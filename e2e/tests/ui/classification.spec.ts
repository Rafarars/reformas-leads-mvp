import { expect, test } from '@playwright/test';
import { ClassificationPage } from '../../pages/classification-page';
import { DashboardPage } from '../../pages/dashboard-page';
import { ReceptionPage } from '../../pages/reception-page';
import { uniqueLead } from '../../support/leads';

// Sin depender de la IA: con o sin key, el lead acaba en la bandeja (clasificado o pendiente) y
// una persona siempre puede decidir. La llamada real al modelo no entra en el CI.
test('a received lead reaches the classification inbox and a person can decide', async ({
    page,
}) => {
    test.setTimeout(120_000);

    const lead = uniqueLead();
    lead.description = `Cambiar la bañera por un plato de ducha ${lead.phone}`;

    const dashboard = new DashboardPage(page);
    const reception = new ReceptionPage(page);
    const classification = new ClassificationPage(page);

    await dashboard.goto();
    await reception.openFromSidebar();
    await reception.register(lead);
    await reception.expectResult('new');

    await classification.openFromSidebar();

    // El trabajo corre en la cola: el panel se refresca solo hasta que aparece.
    await expect(classification.itemFor(lead.description)).toBeVisible({
        timeout: 90_000,
    });

    await classification.review(lead.description, 'installations', 'medium');

    await expect(classification.itemFor(lead.description)).toHaveAttribute(
        'data-state',
        'reviewed',
    );
});
