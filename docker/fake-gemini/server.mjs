// API de IA simulada: responde con la estructura que GeminiLeadClassifier espera.
// Sin dependencias externas: solo el modulo http de Node.
import { createServer } from 'node:http';

const port = Number(process.env.PORT ?? 8080);

const validCategories = new Set([
    'bathroom',
    'kitchen',
    'full_home',
    'facade',
    'installations',
    'other',
]);

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

function extractCategory(body) {
    try {
        const userText = body.contents?.[0]?.parts?.[0]?.text;
        if (typeof userText === 'string') {
            const parsed = JSON.parse(userText);
            const marked = parsed.tipo_marcado_por_la_persona;
            if (typeof marked === 'string' && validCategories.has(marked)) {
                return marked;
            }
        }
    } catch {
        // Si no viene JSON en el prompt, usamos la categoria por defecto.
    }

    return 'bathroom';
}

function generateClassification(category) {
    return {
        category,
        urgency: 'medium',
        confidence: 0.9,
        reason: 'Solicitud clasificada automáticamente según los datos del proyecto.',
        summary: `Reforma de ${category}: solicitud de presupuesto y planificación de obra.`,
        missing_information: ['Medidas del espacio', 'Fotos del estado actual'],
        contradictions: [],
        customer_message:
            'Hola {nombre}, gracias por contactar con nosotros. Hemos recibido tu solicitud. ¿Nos podrías enviar fotos del estado actual y las medidas aproximadas para preparar el presupuesto?',
    };
}

createServer(async (request, response) => {
    const { pathname } = new URL(request.url ?? '/', 'http://localhost');

    // Healthcheck para compose
    if (request.method === 'GET' && pathname === '/health') {
        return json(response, 200, { status: 'ok' });
    }

    // Rutas terminadas en :generateContent (p. ej. /v1beta/models/<model>:generateContent)
    if (request.method === 'POST' && pathname.endsWith(':generateContent')) {
        try {
            const body = await readJson(request);
            const category = extractCategory(body);
            const classification = generateClassification(category);

            return json(response, 200, {
                candidates: [
                    {
                        content: {
                            parts: [
                                {
                                    text: JSON.stringify(classification),
                                },
                            ],
                        },
                    },
                ],
                modelVersion: 'fake-gemini',
            });
        } catch {
            return json(response, 400, { error: 'bad_request' });
        }
    }

    json(response, 404, { error: 'not_found' });
}).listen(port, () => {
    console.log(`fake-gemini escuchando en el puerto ${port}`);
});
