// Cliente de la API externa simulada (docker/fake-crm/server.mjs).
export const fakeCrmURL =
    process.env.E2E_FAKE_CRM_URL ?? 'http://127.0.0.1:8080';

export type FakeCrmControl = {
    outage: boolean;
    rejected: number;
    duplicates: number;
    deliveries: number;
};

export type FakeCrmDelivery = {
    idempotency_key: string | null;
    event: string | null;
    aggregate_id: string | null;
    data: Record<string, unknown> | null;
};

async function call<T>(path: string, init?: RequestInit): Promise<T> {
    const response = await fetch(`${fakeCrmURL}${path}`, init);

    if (!response.ok) {
        throw new Error(`fake-crm ${path} respondió ${response.status}`);
    }

    return (await response.json()) as T;
}

export const fakeCrm = {
    reset: () => call<FakeCrmControl>('/control/reset', { method: 'POST' }),

    setOutage: (enabled: boolean) =>
        call<FakeCrmControl>('/control/outage', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enabled }),
        }),

    control: () => call<FakeCrmControl>('/control'),

    async deliveriesFor(email: string): Promise<FakeCrmDelivery[]> {
        const { deliveries } = await call<{ deliveries: FakeCrmDelivery[] }>(
            '/deliveries',
        );

        return deliveries.filter((delivery) => delivery.data?.email === email);
    },
};
