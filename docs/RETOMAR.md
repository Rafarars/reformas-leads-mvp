# Estado y siguientes pasos

Documento de traspaso: lo necesario para continuar **sin depender de ninguna conversación**.

**Actualizado:** 15 de septiembre de 2026

## Cómo retomar

1. Leer `AGENTS.md` (reglas), `docs/spec.md` (qué se construye) y `docs/ARCHITECTURE.md` (cómo)
2. Buscar en Engram, proyecto `reformas-leads-mvp`, el porqué de cada decisión
3. `make up` y `make verify` para confirmar que todo sigue en verde

## Dónde estamos

|                          |                                                                                     |
| ------------------------ | ----------------------------------------------------------------------------------- |
| Repositorio              | github.com/Rafarars/reformas-leads-mvp (privado)                                    |
| Último commit verificado | `58c4096`, CI en verde                                                              |
| Pruebas                  | Pest 379 · PHPStan nivel 7 sin errores · Playwright 9                               |
| Servicios                | app, queue, scheduler, db, n8n, fake-crm (todos _healthy_)                          |
| URLs locales             | app `127.0.0.1:8010` · n8n `localhost:5678` · CRM simulado `127.0.0.1:8080/control` |
| Usuario de prueba        | `test@example.com` / `password`                                                     |

## Checklist

### Hecho

- [x] Fundación: Docker, salud, CI, configuración validada, Makefile, AGENTS.md, convención hexagonal
- [x] **Paso 1** · API pública `POST /api/v1/leads`: dedupe por teléfono o email, API key por origen
      guardada como hash, idempotencia, límite por cliente
- [x] **Paso 2** · Módulo Recepción en el panel (mismo `LeadReceiver`, la sesión autentica)
- [x] **Paso 3** · Outbox transaccional, entrega con backoff y jitter, circuit breaker, DLQ,
      barrido `outbox:relay`
- [x] **Paso 4** · API externa simulada (`docker/fake-crm`) con interruptor de caída
- [x] **Paso 5** · Panel de Resiliencia: simular caída, inyectar leads, reintentar apartados
- [x] **Paso 6** · Clasificación con IA (módulo `src/Leads/Classification`)
    - [x] Puerto `LeadClassifier`; adaptador `GeminiLeadClassifier` con esquema de salida forzado y validado
    - [x] Categoría, urgencia, confianza y **motivo**; a la IA solo va el tipo, la zona, el presupuesto y la descripción
    - [x] Asíncrona: el evento `lead.received` encola `ClassifyLeadJob`; recibir nunca espera a la IA
    - [x] Caída → reintentos con backoff (5 excepciones); respuesta inválida, sin key o reintentos agotados → revisión humana
    - [x] Confianza < `CLASSIFICATION_MIN_CONFIDENCE` (0,7) → revisión humana
    - [x] Pantalla **Clasificación**: bandeja de revisión para confirmar o corregir
    - [x] Límite de peticiones por minuto a Gemini (`GEMINI_REQUESTS_PER_MINUTE`)
    - [x] Probado contra Gemini real con leads ficticios (fuga urgente, lead vago, intento de inyección)
- [x] **Paso 6b** · En la misma llamada a Gemini: resumen, lo que falta para presupuestar, datos que no cuadran y
      **borrador del mensaje al cliente**
    - [x] El modelo escribe `{nombre}`; el nombre real se pone en el panel y nunca viaja a la IA
    - [x] El borrador queda `pending`; una persona lo edita y lo aprueba. Aprobar publica
          `lead.customer_message_approved` en el outbox (lo recogerá n8n en el paso 7). **Nunca se envía solo**
    - [x] Probado con Gemini real: detecta "3.000 € para reformar 120 m²" y "marcó cocina, pero es eléctrico"

### Pendiente

- [x] **Paso 7 · Avisos con n8n** (guía en `docs/N8N.md`, historia en `docs/BITACORA.md`)
    - [x] Outbox con **un mensaje y un circuit breaker por destino** (`crm`, `team`, `customer`)
    - [x] Evento `lead.classified` (también para revisión humana); `lead.customer_message_approved` con destinatario
    - [x] Google Cloud (proyecto, Gmail API, OAuth) y credenciales de Gmail y Telegram en n8n
    - [x] Flujos `Reformas · Cliente` (Gmail) y `Reformas · Equipo` (Telegram), configurados por Rafael,
          **publicados** y probados de punta a punta
    - [x] Resiliencia observada: 8 avisos acumulados con 404 se entregaron solos al publicar
- [x] **Paso 7b · Flujo unificado** (`Reformas · Avisos`)
    - [x] Webhook `reformas-events` → **Switch** por `body.event` → Telegram / Gmail / `Stop and Error`
    - [x] `DELIVERY_TEAM_URL` y `DELIVERY_CUSTOMER_URL` apuntan a `/webhook/reformas-events`; los flujos
          viejos quedaron despublicados
    - [x] Flujo exportado a `n8n/workflows/reformas-avisos.json` (sin secretos, con el `chat_id` sustituido)
    - [x] Verificado de punta a punta: lead nuevo → Telegram; borrador aprobado → correo; evento inventado → 500
    - [ ] Borrador del correo en párrafos (instrucción a Gemini)
    - [ ] Bot de Telegram propio del proyecto y revocar el token actual (expuesto en la sesión con agy)
    - [ ] Quitar `GMAIL_API_KEY` del `.env` (sobra)
- [x] **Paso 8 · Fotos y planos** (módulo `src/Leads/Attachment`)
    - [x] Endpoint propio: `POST /api/v1/leads/{lead}/attachments` y subida desde el panel de Clasificación
    - [x] La ruta del archivo la calcula el dominio con identificadores; lista blanca JPG/PNG/WEBP/PDF y 10 MB
    - [x] Si la transacción falla, **el archivo se borra**: el disco no tiene rollback
    - [x] Disco privado; descarga por una ruta con sesión, no por URL pública
    - [x] Pruebas: dominio, caso de uso (incluida la limpieza), contrato del repositorio, API, panel y E2E
- [ ] **Despliegue** (plan completo en `docs/DEPLOY.md`, `compose.prod.yml` listo)
    - [ ] Crear la VM gratuita en Oracle Cloud (tarjeta solo para verificar; si la rechaza, alternativa
          sin tarjeta al final de `DEPLOY.md`)
    - [ ] Dominio, HTTPS y secretos en el servidor
    - [ ] n8n en producción: rehacer la credencial de Gmail con la URI nueva e importar el flujo
    - [ ] Proteger `/control` y el editor de n8n; copias de seguridad
- [ ] **Cierre**
    - [ ] Actualizar README con la demo y, si procede, un vídeo corto
    - [ ] Nodo de IA en n8n para algo no crítico con un modelo gratuito (Groq `qwen/qwen3.8-27b`; ver `docs/FUTURE.md`)
    - [ ] Extraer al playbook lo validado en los pasos 6 a 8

### Deuda anotada en `docs/FUTURE.md`

- [ ] Extensión `intl` en la imagen (`Number::format` falla en el contenedor)
- [ ] Dos envíos simultáneos de la misma persona (bloqueo consultivo de PostgreSQL)
- [ ] Limpiar `idempotency_keys` con más de 24 h
- [ ] `make verify-clean` contra una base vacía
- [ ] Autenticar `/control` del CRM simulado si alguna vez se despliega
- [ ] WhatsApp como canal al cliente

## Decisiones cerradas — no volver a discutirlas

- Núcleo en `src/` (namespace `Reformas\`); controladores en `app/Http/Controllers`, uno por acción
- Casos de uso `final readonly class` con `__invoke()`; errores `*Error` en dominio, `*Exception` en infraestructura
- **Nunca citar código privado de un empleador** en el repositorio; fuentes públicas como Codely sí
- El formulario no lleva API key en el navegador: entra por una ruta web al mismo caso de uso
- La entrega decide rendirse por antigüedad (24 h desde el último reintento manual), no por intentos
- Herramientas de demo (`fake-crm`, `FakeCrmClient`, `DemoLeads`) fuera de `src/`
- Modelo por defecto `gemini-3.5-flash-lite`: en el tier gratuito da 15 peticiones/min y 500/día (Flash solo 5 y 20) y clasificó igual de bien. `gemini-2.5-flash` ya no se ofrece a cuentas nuevas (404)
- Un 4xx de Gemini que no sea 408/429 no se reintenta (key, modelo o parámetro): va directo a revisión
- Los leads de la demo de resiliencia (`source=demo`) y los contactos repetidos **no se clasifican**
- La clasificación vive en su propia tabla (`lead_classifications`), no en columnas de `leads`
- Opción B para los avisos: un mensaje del outbox por destino. Descartada la A (todo a n8n y n8n reenvía al
  CRM): un fallo del CRM hacía reintentar todo y duplicaba los avisos de Telegram
- Los nodos de n8n los configura Rafael con guía paso a paso; no se importan flujos ni credenciales por comando
- La IA que decide (clasificación) vive en Laravel; n8n solo reparte avisos
- Los Telegrams de las E2E en local se aceptan; no se filtra por `@example.com`
- Otra herramienta que continúe el trabajo (p. ej. agy) debe leer este documento primero y proponer antes de
  cambiar la arquitectura: ver los errores registrados en `docs/BITACORA.md`
- Automatismos nuevos (Makefile, enganches) y reglas de `AGENTS.md`: **se proponen antes de escribirlos**
