# Spec — MVP de recepción y clasificación de leads de reformas

Caso práctico Grupo LYN (bloque 6 del vídeo asincrónico), construido de verdad.
Doble propósito: portafolio demostrable y banco de pruebas para defender en entrevista
el diseño resiliente ante caída de una API externa.

---

## 1. Problema

Entra un lead de reforma con: nombre, teléfono, email, tipo de reforma, zona,
presupuesto aproximado, fotos, planos y descripción del proyecto.

Hay que recibirlo, validarlo, no duplicarlo, no perderlo nunca, clasificarlo con IA,
avisar al equipo y dejar trazabilidad — sobreviviendo a que los sistemas externos
se caigan durante minutos sin perder un solo registro.

## 2. Objetivo del MVP

No es "un formulario que guarda leads". Eso lo hace cualquiera.

El MVP tiene que **demostrar en vivo** tres cosas que se preguntan en entrevista:

1. Que ningún lead se pierde aunque la API externa esté caída 30 minutos.
2. Que nada se duplica, ni por reenvío del usuario ni por reintento del sistema.
3. Que la IA clasifica con criterios de negocio explícitos y auditables, y que lo
   que toca al cliente final queda con revisión humana.

**Criterio de éxito:** poder apagar la API externa, inyectar 15 leads, mostrar la cola
creciendo sin pérdidas, revivir la API y ver cómo se drena sola, sin duplicados.

## 3. Alcance

### Dentro

- Formulario público de captación (React).
- Endpoint único de ingesta, consumido por igual por el formulario, por un webhook
  externo simulado (Facebook Lead Ads) y por n8n.
- Validación de campos obligatorios y control de duplicados.
- Persistencia inmediata antes de cualquier procesamiento.
- Clasificación con IA: tipo, prioridad, información faltante, contradicciones, resumen.
- Outbox + cola con reintentos, backoff exponencial con jitter, circuit breaker y DLQ.
- Panel interno: leads, estados, cola, reintentos, DLQ, y controles de simulación de fallo.
- n8n: notificación al equipo y borrador del mensaje de seguimiento.
- Trazabilidad: historial de estados por lead.

### Fuera (fase 2, declarado)

- Envío automático del mensaje al cliente (queda en revisión humana por diseño).
- Carga de fotos y planos: **el diseño lo contempla** (endpoint separado, transacción,
  limpieza del archivo huérfano) pero la implementación va al final. Si no llega, se
  entrega documentado como fase 2.
- Multi-tenant, roles finos, i18n.

## 4. Arquitectura

```
Formulario React ─┐
Webhook externo  ─┼──> POST /api/leads ──> [validar] ──> [dedupe] ──> [persistir] ──> 200 OK
n8n              ─┘                                                        │
                                                                           │ (transacción)
                                                                           ▼
                                                                    tabla outbox
                                                                           │
                                                              worker de cola (async)
                                                                           │
                                            ┌──────────────────────────────┼───────────────┐
                                            ▼                              ▼               ▼
                                    clasificar con IA            notificar a n8n      webhooks
                                            │                              │
                                     reintentos + backoff + circuit breaker │
                                            │                              │
                                            └──────────> DLQ + alerta <────┘
```

**Regla fundacional:** el endpoint responde `200 OK` en cuanto persistió. Ninguna
lógica de negocio lenta ni llamada externa ocurre dentro del request. Todo lo demás
es asíncrono y reintentable.

## 5. Modelo de datos

- `leads` — datos del lead, `state`, `fingerprint` (hash de teléfono+email normalizados), `contact_count`.
- `lead_classifications` — resultado de IA: tipo, prioridad, faltantes, contradicciones, resumen, **motivo**, modelo usado, costo, latencia.
- `lead_state_history` — cada transición con fecha, actor y motivo. Trazabilidad.
- `outbox_messages` — `id`, `type`, `payload`, `idempotency_key`, `status`, `attempts`, `next_attempt_at`, `last_error`.
- `jobs` / `failed_jobs` — cola nativa de Laravel. `failed_jobs` **es** el DLQ, y es consultable por el panel.
- `documents` — fotos y planos (fase 2 en implementación, presente en el diseño).

## 6. Resiliencia — el corazón del MVP

Cada pieza responde una pregunta concreta de entrevista.

| Pieza                                       | Qué resuelve                                               |
| ------------------------------------------- | ---------------------------------------------------------- |
| Persistir antes de procesar                 | El lead existe aunque todo lo demás falle                  |
| Outbox en la misma transacción              | El mensaje a enviar nunca se pierde por una caída          |
| Reintentos con backoff exponencial + jitter | Sobrevive caídas largas sin tumbar la API al revivir       |
| Clave de idempotencia por mensaje           | At-least-once en el envío, exactly-once en el efecto       |
| Circuit breaker                             | Deja de golpear lo que está caído; permite recovery limpio |
| DLQ + alerta                                | Lo irrecuperable se aparta con su error, nunca se descarta |
| Replay desde el panel                       | Reprocesar a mano tras arreglar la causa raíz              |

**Dedupe en dos niveles:**

- _Entrada:_ `fingerprint` sobre teléfono+email normalizados. Si existe, no se crea otro lead: se actualiza y se registra el segundo contacto.
- _Salida:_ `idempotency_key` por mensaje del outbox. El reintento no produce doble notificación.

## 7. La IA

Solo en la parte interpretativa: clasificar, detectar huecos y contradicciones, redactar.

- Modelo rápido y económico para el caso normal (alto volumen, tarea acotada).
- Modelo más capaz solo cuando la descripción es ambigua o contradictoria.
- El prompt lleva los criterios reales del negocio: tipos de reforma, rangos de
  presupuesto por tipo, zonas.
- **Devuelve siempre el motivo de su elección.** Sin motivo no hay forma de auditar
  si se equivocó y por qué.
- Salida con esquema estricto y validada. Si no valida, se reintenta; si insiste, va a DLQ.
- Si la IA falla o tarda, **el lead igual queda guardado y visible** para el equipo, sin clasificar.
- El mensaje al cliente se genera pero **no se envía**: queda en revisión humana.
- **Solo datos ficticios.** El tier gratuito de la API de Gemini puede usar lo que recibe
  para mejorar sus productos. Ningún lead real pasa por la IA de este proyecto.

## 7b. API pública `v1`

El contrato del sistema es **`POST /api/v1/leads`**. El formulario React es un cliente más,
igual que un webhook externo (Facebook Lead Ads) o n8n: no hay rutas internas separadas.

| Pieza                           | Qué resuelve                                                                      |
| ------------------------------- | --------------------------------------------------------------------------------- |
| Versionado en la ruta           | El día que cambie el contrato, quien consume `v1` no se rompe                     |
| API key por origen              | Cada consumidor tiene su clave, rotable por separado                              |
| Campo `source`                  | Trazabilidad por canal: qué origen trae leads y cuáles convierten                 |
| Rate limiting por key           | Un integrador roto no tumba a los demás                                           |
| `Idempotency-Key` en la entrada | Si el emisor reintenta porque perdió nuestro `200 OK`, no se crea un segundo lead |

La idempotencia se aplica **en los dos sentidos**: al recibir (`Idempotency-Key`) y al enviar
(clave por mensaje del outbox).

## 8. n8n

Levantado en Docker. Recibe el webhook de lead validado y se encarga de conectar y notificar:

- Notificación al canal del equipo con prioridad y resumen.
- Borrador del mensaje de seguimiento a la cola de revisión humana.
- Alerta cuando un lead lleva demasiado tiempo sin contacto.

La lógica de negocio (validar, deduplicar, persistir) **no** vive en n8n: vive en código,
porque necesita control exacto y transacciones.

## 9. Panel interno

Es la pieza de demostración. Muestra:

- Bandeja de leads con estado, prioridad y clasificación.
- Detalle del lead con su historial completo de estados.
- Estado de la cola: pendientes, en reintento, próximo intento, número de intento.
- DLQ con el error de cada mensaje y botón de replay.
- Estado del circuit breaker.
- **Controles de demo:** apagar/encender la API externa simulada, e inyectar N leads de golpe.

## 10. Métricas

1. Leads recibidos sin caída.
2. Tiempo entre que llega el lead y alguien lo contacta.
3. **Clasificaciones corregidas a mano por el equipo.** La más honesta: si corrigen
   mucho, el prompt necesita ajuste, no es que "funcione".

## 11. Stack

- Laravel 12 (starter kit React → Inertia), PHP 8.4
- React + TypeScript, Tailwind v4, shadcn/ui
- MySQL 8
- Cola en base de datos (visible y consultable desde el panel; sin Redis a propósito)
- n8n en Docker
- API de IA con esquema de salida estricto

## 12. Orden de construcción

1. Ingesta: endpoint, validación, dedupe, persistencia, historial de estados.
2. Formulario React contra ese mismo endpoint.
3. Outbox + worker + reintentos + backoff + idempotencia + DLQ.
4. API externa simulada con interruptor de caída.
5. Panel interno con cola, DLQ y controles de demo.
6. Clasificación con IA y criterios de negocio.
7. n8n: notificación y borrador de seguimiento.
8. Fotos y planos, si el tiempo alcanza.

Cada paso deja algo demostrable. Se corta donde se corte, lo construido funciona.
