# Estado y siguientes pasos

Documento de traspaso: lo necesario para continuar **sin depender de ninguna conversación**.

**Actualizado:** 22 de septiembre de 2026

## ⚠️ Punto de retorno inmediato: el despliegue

**La app está viva en Render. La base de n8n ya está migrada en Supabase y la causa de que n8n no
arrancase está encontrada y probada en local.** Todo el detalle en
[`DEPLOY-BITACORA.md`](DEPLOY-BITACORA.md) §6 y §7.

1. ~~**Rafael añade tres variables** en Render → `reformas-n8n` → _Environment_~~ ✅ **Hecho y verificado
   desde fuera** el 20 de septiembre de 2026: `/healthz` da 200, `/` y `/rest/settings` dan 404 (la
   interfaz no se sirve), `POST /webhook/reformas-events` da 404 (enruta, falta publicar el flujo) y 25
   peticiones seguidas sin un solo fallo ni reinicio.

    | Variable              | Valor                                                    |
    | --------------------- | -------------------------------------------------------- |
    | `N8N_RUNNERS_ENABLED` | `false`                                                  |
    | `N8N_DISABLE_UI`      | `true`                                                   |
    | `NODE_OPTIONS`        | `--max-old-space-size=320` (sustituye al `256` anterior) |

    Verificar después: `https://reformas-n8n.onrender.com/healthz` debe dar **200**. Ya están en
    `render.yaml`, pero el panel manda hasta que se sincronice el blueprint.

2. **La configuración de n8n se hace desde el n8n local**, apuntando a la base de Supabase (el de Render
   ya no sirve el editor). Allí: credenciales de **Telegram** (bot `@leads_reformas_mvp_bot`) y **Gmail**
   (añadir la URI `https://reformas-n8n.onrender.com/rest/oauth2-credential/callback` en Google Cloud),
   importar `n8n/workflows/reformas-avisos.json`, poner el `chat_id` real y **publicarlo**. Al terminar,
   **apagar ese contenedor**: si se queda encendido, consume conexiones del pooler de Supabase.
3. Rafael pone `ADMIN_EMAIL` y `ADMIN_PASSWORD` en Render (servicio `reformas-leads` → Environment) para
   poder entrar. El registro público está cerrado en producción.
4. Verificar de punta a punta: lead → clasificación → Telegram; aprobar → Gmail; foto → Supabase Storage.
5. Configurar el ping en cron-job.org (`/health` cada 10 min, L-V 9:00-18:00 hora española).
6. Borrar el servicio viejo `n8n` del proyecto `n8n_selfhost_prueba` de Render.

**Nunca pedir ni pegar `SUPABASE_DB_PASSWORD` ni `N8N_ENCRYPTION_KEY` en el chat.**

Publicar = `git checkout production && git merge main && git push && git checkout main`, **solo con el CI
de `main` en verde** (Render despliega sin esperar al CI).

**Fase 0 cerrada y publicada** (`d8abb09`, en `main` y `production`): el enlace de registro solo aparece si
el registro está abierto, la recuperación de contraseña está apagada en producción, el sondeo del panel es
silencioso y las pruebas E2E corren en paralelo sin depender del estado de la base.

> **Siguiente tramo de trabajo:**
>
> 1. **Rotar `N8N_ENCRYPTION_KEY`** siguiendo [`DEPLOY.md`](DEPLOY.md) §7. Recifrar antes las credenciales:
>    cambiar solo la variable deja Telegram y Gmail sin poder descifrarse, y el panel no lo detecta.
> 2. **[`PLAN.md`](PLAN.md) Fase 1**: avisos de Telegram por usuario.

## Cómo retomar

1. Leer `AGENTS.md` (reglas), `docs/spec.md` (qué se construye) y `docs/ARCHITECTURE.md` (cómo)
2. Buscar en Engram, proyecto `reformas-leads-mvp`, el porqué de cada decisión
3. `make up` y `make verify` para confirmar que todo sigue en verde

## Dónde estamos

|                                |                                                                                                                                   |
| ------------------------------ | --------------------------------------------------------------------------------------------------------------------------------- |
| Repositorio                    | github.com/Rafarars/reformas-leads-mvp (privado)                                                                                  |
| Último commit verificado       | `2adb397` (`main`), CI en verde. Publicado en `production`: `2adb397`                                                             |
| Pruebas                        | Pest 511 · PHPStan nivel 7 sin errores · Playwright 14 (en paralelo, 2 workers, con la IA simulada)                               |
| Servicios                      | app, queue, scheduler, db, n8n, fake-crm (todos _healthy_)                                                                        |
| URLs locales                   | app `127.0.0.1:8010` · n8n `localhost:5678` · CRM simulado `127.0.0.1:8080/control`                                               |
| Usuario de prueba (solo local) | `test@example.com` / `password`                                                                                                   |
| Producción                     | app `https://reformas-leads.onrender.com` (viva) · n8n `https://reformas-n8n.onrender.com` (**vivo**, sin editor: `/healthz` 200) |

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

- [x] **Fase 1 · Vincular Telegram por usuario** (`caa74fd`): módulo `src/Notifications/TelegramLink`
      con token de un solo uso guardado con hash. **Sin interfaz todavía**: la pantalla es la Fase 3 y el
      endpoint que llama n8n, la Fase 4. Por eso no está publicado en `production`
- [x] **Fase 2 · El abanico de entrega** (`91f9d1b`): un mensaje de outbox por destinatario, marcado
      con `fan_out` en la configuración, y el **HTTP 422** de n8n como fallo permanente que va a la DLQ
      sin abrir el circuito del equipo. El panel avisa cuando no hay nadie conectado
- [x] **IA simulada en local y CI** (`7f4395d`): las E2E ya no dependen de la API real de Gemini ni
      gastan su cuota. Interruptor `GEMINI_BASE_URL` en `docker-compose.yml`
- [x] **Fase 3 · Pantalla para conectarse** (`4ff6354`): Ajustes → Telegram, con el enlace de un solo
      uso, el estado y el botón de desconectar, más `POST /api/v1/telegram/link` para el bot
- [x] **La clave del bot se crea sola al arrancar** (`ae52c21`): `app:api-client:ensure-bot` desde
      `BOT_API_KEY`, porque el plan gratuito de Render no da consola para crearla a mano
- [x] **Aviso para conectar Telegram** (`2adb397`, publicado): banda en todo el panel mientras el
      usuario no tenga su Telegram vinculado; desaparece sola al vincularse. El dato es una prop
      compartida perezosa, así que los sondeos cada 3 s no hacen consultas de más
- [ ] **CRM simulado en producción**: desplegarlo en Render como tercer servicio, con los controles
      protegidos por token (`docs/FUTURE.md`, "Autenticar el CRM simulado"). Hoy cada lead deja un
      aviso al CRM que falla con 404 y los botones de Resiliencia no funcionan en producción
- [x] **Fase 4 · n8n** (hecha por Rafael el 30-sep-2026). Guía completa, con las trampas, en
      [`N8N.md`](N8N.md) §9. Por orden:
    1. Generar la clave: `echo "rl_$(openssl rand -hex 32)"`, y ponerla en Render (`BOT_API_KEY`) y en
       el `.env` local
    2. Flujo nuevo: `Telegram Trigger` (_message_) → filtro de los que empiezan por `/start ` →
       `HTTP Request` a `POST /api/v1/telegram/link` con `Authorization: Bearer <clave>` y cuerpo
       `{token, chat_id}` → responder "Listo, ya recibirás los avisos"
    3. En el flujo de avisos, el `chatId` fijo pasa a la expresión `{{ $json.body.data.chat_id }}`
    4. El nodo de Telegram con _Continue (using error output)_: si el error dice _bot was blocked_ o
       _chat not found_, responder **422** (el `Webhook` debe estar en _Respond: Using Respond to
       Webhook node_); cualquier otro error sigue fallando y se reintenta
    5. Exportar los dos flujos a `n8n/workflows/` sin secretos y con el `chat_id` sustituido
- [x] **Publicado** `1d711e0` (30-sep-2026). Comprobado desde fuera: Ajustes → Telegram existe, el
      endpoint rechaza sin clave (401) y con `BOT_API_KEY` responde "token no encontrado" (404), así que
      la migración corrió y la clave del bot se creó al arrancar. **Desde aquí no salen avisos de equipo
      hasta que alguien se conecte**

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
    - [x] Bot de Telegram propio del proyecto (`@leads_reformas_mvp_bot`); el `chat_id` no cambia
    - [ ] Revocar el token del bot antiguo (rompe la otra integración hasta actualizarla allí)
    - [ ] Quitar `GMAIL_API_KEY` del `.env` (sobra)
    - [ ] **Rotar `N8N_ENCRYPTION_KEY`** recifrando las credenciales ([`DEPLOY.md`](DEPLOY.md) §7)
- [x] **Paso 8 · Fotos y planos** (módulo `src/Leads/Attachment`)
    - [x] Endpoint propio: `POST /api/v1/leads/{lead}/attachments` y subida desde el panel de Clasificación
    - [x] La ruta del archivo la calcula el dominio con identificadores; lista blanca JPG/PNG/WEBP/PDF y 10 MB
    - [x] Si la transacción falla, **el archivo se borra**: el disco no tiene rollback
    - [x] Disco privado; descarga por una ruta con sesión, no por URL pública
    - [x] Pruebas: dominio, caso de uso (incluida la limpieza), contrato del repositorio, API, panel y E2E
- [ ] **Despliegue** (guía `docs/DEPLOY.md`, historia y decisiones `docs/DEPLOY-BITACORA.md`)
    - [x] Decisión: **Render + Supabase**, coste 0 y sin tarjeta. Oracle imposible (no admite Venezuela),
          Google exige cuenta de facturación con tarjeta
    - [x] Rama `production`; `render.yaml`; arranque con la cola y el planificador dentro del contenedor
          (`docker/render/start.sh`); etapa `render` del Dockerfile sin _capabilities_
    - [x] Supabase: proyecto, bucket privado `attachments`, Data API desactivada
    - [x] Archivos por S3 (`ATTACHMENTS_DISK=s3`) y `trustProxies` para servir los assets por https
    - [x] Administrador por variables de entorno (`app:admin:ensure`) y registro cerrado en producción
    - [x] La **app** responde y está conectada a Supabase
    - [x] **n8n en Render: vivo** (§6 de `DEPLOY-BITACORA.md`: sin ejecutor de tareas y sin editor)
    - [x] `ADMIN_EMAIL` y `ADMIN_PASSWORD` en Render, para poder entrar
    - [x] `N8N_BASE_URL` con la **URL completa** (`https://…`). El blueprint no puede componerla:
          `fromService` con `hostport` da "host:puerto" sin esquema y el panel marca n8n como caído
    - [x] Credenciales de Telegram y Gmail en el n8n de producción, importar y publicar el flujo
    - [x] **Probado de punta a punta en producción**: lead → Gemini → Telegram; aprobar → Gmail
    - [ ] Ping por horario (cron-job.org) y borrar el servicio viejo `n8n` de Render
    - [ ] Probar la subida de fotos en producción (Supabase Storage)
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
- [ ] Miniatura de las fotos en el panel de Clasificación (pedida por Rafael)
- [ ] Groq detrás del puerto `LeadClassifier` (`CLASSIFIER_DRIVER`), más peticiones que Gemini

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
- **Despliegue:** Render + Supabase. Nunca desplegar desde `main`: se publica desde `production`
- **No dejar usuario de prueba en producción**; el administrador sale de variables de entorno
- Un `push` solo sale si `gitleaks` pasa: encadenar con `&&`, nunca con `;`
- `make secrets` escanea el historial: pasarlo **después** del commit
