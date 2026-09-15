// API externa simulada: el "CRM" al que se entregan los avisos de leads.
// Sin dependencias: solo el módulo http de Node. Tiene un interruptor de caída para demostrar que
// el outbox no pierde nada cuando el destino no responde.
import { createServer } from 'node:http';

const port = Number(process.env.PORT ?? 8080);

const state = {
    outage: false,
    rejected: 0,
    duplicates: 0,
    deliveries: [],
    seenKeys: new Set(),
};

function json(response, status, body) {
    response.writeHead(status, { 'Content-Type': 'application/json' });
    response.end(JSON.stringify(body));
}

function readJson(request) {
    return new Promise((resolve, reject) => {
        let raw = '';
        request.on('data', (chunk) => {
            raw += chunk;
        });
        request.on('end', () => {
            try {
                resolve(raw === '' ? {} : JSON.parse(raw));
            } catch (error) {
                reject(error);
            }
        });
        request.on('error', reject);
    });
}

function controlState() {
    return {
        outage: state.outage,
        rejected: state.rejected,
        duplicates: state.duplicates,
        deliveries: state.deliveries.length,
    };
}

const routes = {
    'GET /health': (request, response) => json(response, 200, { status: 'ok' }),

    'GET /control': (request, response) => json(response, 200, controlState()),

    'POST /control/outage': async (request, response) => {
        const body = await readJson(request);
        state.outage = body.enabled === true;

        json(response, 200, controlState());
    },

    'GET /deliveries': (request, response) =>
        json(response, 200, { deliveries: state.deliveries }),

    // Deja el simulador limpio, para que cada demo o prueba empiece de cero.
    'POST /control/reset': (request, response) => {
        state.outage = false;
        state.rejected = 0;
        state.duplicates = 0;
        state.deliveries = [];
        state.seenKeys.clear();

        json(response, 200, controlState());
    },

    'POST /webhooks/leads': async (request, response) => {
        // Caída simulada: responde como un servicio que no está disponible.
        if (state.outage) {
            state.rejected += 1;

            return json(response, 503, { error: 'service_unavailable' });
        }

        const body = await readJson(request);
        const key = request.headers['idempotency-key'];

        // Como un receptor real: el mismo aviso reintentado no se registra dos veces.
        if (typeof key === 'string' && state.seenKeys.has(key)) {
            state.duplicates += 1;

            return json(response, 200, { received: true, duplicate: true });
        }

        if (typeof key === 'string') {
            state.seenKeys.add(key);
        }

        state.deliveries.push({
            idempotency_key: typeof key === 'string' ? key : null,
            received_at: new Date().toISOString(),
            event: body.event ?? null,
            aggregate_id: body.aggregate_id ?? null,
            data: body.data ?? null,
        });

        json(response, 202, { received: true, duplicate: false });
    },
};

createServer(async (request, response) => {
    const { pathname } = new URL(request.url ?? '/', 'http://localhost');
    const route = routes[`${request.method} ${pathname}`];

    if (route === undefined) {
        return json(response, 404, { error: 'not_found' });
    }

    try {
        await route(request, response);
    } catch {
        json(response, 400, { error: 'bad_request' });
    }
}).listen(port, () => {
    console.log(`fake-crm escuchando en el puerto ${port}`);
});
