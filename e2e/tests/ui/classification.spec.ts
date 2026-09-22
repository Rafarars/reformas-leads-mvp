import { expect, test } from '../../support/test';
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

    // Una foto del baño: es lo que falta para presupuestar, y se revisa junto a la clasificación.
    await classification.attach(lead.description, 'photo', {
        name: 'bano.png',
        mimeType: 'image/png',
        buffer: Buffer.from(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c6360000002000100055c0b790000000049454e44ae426082',
            'hex',
        ),
    });
    await expect(classification.attachments(lead.description)).toHaveText(
        /bano\.png/,
    );

    await classification.review(lead.description, 'installations', 'medium');

    await expect(classification.itemFor(lead.description)).toHaveAttribute(
        'data-state',
        'reviewed',
    );
});

test('select dropdown stays open across polling intervals', async ({
    page,
}) => {
    test.setTimeout(120_000);

    const lead = uniqueLead();
    lead.description = `Probar que el desplegable no se cierra ${lead.phone}`;

    const dashboard = new DashboardPage(page);
    const reception = new ReceptionPage(page);
    const classification = new ClassificationPage(page);

    await dashboard.goto();
    await reception.openFromSidebar();
    await reception.register(lead);
    await reception.expectResult('new');

    await classification.openFromSidebar();

    const item = classification.itemFor(lead.description);
    await expect(item).toBeVisible({ timeout: 90_000 });
    await item.scrollIntoViewIfNeeded();
    await item.evaluate((el) => el.scrollIntoView({ block: 'center' }));

    const leadId = await item.getAttribute('data-lead');
    const categoryTrigger = page.getByTestId(
        `classification-review-${leadId}-category`,
    );
    const option = page.getByTestId(
        `classification-review-${leadId}-category-kitchen`,
    );

    // Abre el desplegable
    await categoryTrigger.evaluate((el) =>
        el.scrollIntoView({ block: 'center' }),
    );
    await categoryTrigger.click();
    await expect(option).toBeVisible();

    // Espera 4 segundos (más que los 3s de POLL_EVERY_MS)
    await page.waitForTimeout(4_000);

    // Con el sondeo sin pausar, el re-render de usePoll desmonta el portal y esto debe fallar
    await expect(option).toBeVisible();
    await option.click();
});
