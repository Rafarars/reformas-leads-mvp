import type { LeadForm } from '../pages/reception-page';

// La base de las pruebas end-to-end no se borra entre corridas: cada lead lleva un teléfono y un
// email únicos, o la segunda corrida lo encontraría ya registrado.
export function uniqueLead(): LeadForm {
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
