import type { LeadForm } from '../pages/reception-page';

// Los leads generados para pruebas E2E usan el dominio @e2e.test para permitir
// su purga hermética mediante e2e:purge-leads sin tocar leads de desarrollo.
export function uniqueLead(): LeadForm {
    const seed = `${Date.now()}${Math.floor(Math.random() * 1000)}`.slice(-8);

    return {
        name: 'Lucía Fernández',
        phone: `6${seed}`,
        email: `lucia.${seed}@e2e.test`,
        renovationType: 'bathroom',
        zone: 'Valencia',
        budget: 8500,
        description: 'Cambiar la bañera por un plato de ducha',
    };
}
