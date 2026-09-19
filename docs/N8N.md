# n8n — avisos al equipo y al cliente

Guía reproducible de todo lo configurado fuera del código: Google Cloud, el bot de Telegram y los
flujos de n8n. Los flujos se configuraron **a mano, paso a paso**, a propósito: el objetivo era
aprender n8n, no importar un JSON.

**Ningún secreto vive en este documento ni en el repositorio.** Tokens y credenciales OAuth están en
el gestor cifrado de n8n (`N8N_ENCRYPTION_KEY`) o en `.env`, que no se versiona.

---

## Qué hace n8n y qué no

```
LARAVEL (lógica de negocio, validación, reintentos)            N8N (solo conecta canales)
──────────────────────────────────────────────────            ─────────────────────────────────────────
lead.received ─────────────────► destino crm      ─► CRM simulado
lead.classified ───────────────► destino team     ─┐
lead.customer_message_approved ► destino customer ─┴─► /webhook/reformas-events
                                                        └─ Switch (body.event) ─┬─ equipo  ─► Telegram
                                                                                ├─ cliente ─► Gmail
                                                                                └─ extra   ─► Stop and Error
```

**Un solo flujo en n8n, dos destinos en Laravel.** El reparto por canal lo hace el Switch; los reintentos y
el circuit breaker siguen siendo por destino, así que un fallo de Gmail no frena los avisos de Telegram.

- **La IA no corre en n8n.** Gemini se llama desde Laravel (`GeminiLeadClassifier`): la clasificación
  decide prioridades y qué se escribe al cliente, así que necesita pruebas, validación estricta contra
  el dominio, reintentos y minimización de datos. n8n recibe el resultado ya validado.
- **Los reintentos no son de n8n.** Viven en el outbox de Laravel, con un circuit breaker por destino.
  n8n solo tiene que responder con error cuando algo falla, para que Laravel reintente.

## Formato de lo que llega a n8n

Toda entrega del outbox es un `POST` con este cuerpo (en n8n, `$json.body`):

```json
{
    "id": "uuid del mensaje del outbox (también va en la cabecera Idempotency-Key)",
    "event": "lead.classified",
    "aggregate_id": "uuid del lead",
    "occurred_at": "2026-09-17T03:33:21+00:00",
    "data": { "...": "depende del evento" }
}
```

| Evento                           | `data`                                                                                                                                                         |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `lead.classified`                | `status`, `category`, `urgency`, `confidence`, `reason`, `summary`, `missing_information[]`, `contradictions[]`, `review_reason`, `customer{name,email,phone}` |
| `lead.customer_message_approved` | `lead_id`, `message`, `approved_by`, `customer{name,email,phone}`                                                                                              |

`lead.classified` **no lleva el borrador** del mensaje: el aviso al equipo es para decidir y actuar, no
para leer un correo todavía sin aprobar. Se emite también cuando el lead queda para revisión humana
(`status: needs_review`, sin categoría), para que nunca espere sin que nadie lo sepa.

---

## 1. Google Cloud: credencial OAuth2 para Gmail

**Coste: ninguno.** La Gmail API y OAuth son gratuitos. **No hace falta** la prueba de 300 $ que ofrece
la consola (pide tarjeta).

1. `console.cloud.google.com` → **Selecciona un proyecto → Proyecto nuevo** → nombre `reformas-leads`.
   En **Recurso superior** se deja **Sin organización** (cuenta personal de Gmail).
2. **APIs y servicios → Biblioteca** → **Gmail API** → **Habilitar**.
3. **Pantalla de consentimiento** (Google la llama ahora _Google Auth Platform_): tipo **Externo**,
   nombre de la app, correo de asistencia y de contacto. Los permisos (_scopes_) se dejan vacíos: los
   pide n8n al conectar.
4. **Público → Usuarios de prueba → Agregar usuarios**: el propio Gmail. Sin esto Google bloquea el
   inicio de sesión (la app está en modo prueba).
5. **Credenciales → Crear credenciales → ID de cliente de OAuth** (tipo de dato: _Datos de los
   usuarios_) → **Aplicación web** → **URI de redireccionamiento autorizados**:
   `http://localhost:5678/rest/oauth2-credential/callback`
    - Esa URI no se obtiene de ningún sitio: es la dirección fija de n8n a la que Google devuelve la
      autorización.
    - _Orígenes autorizados de JavaScript_ se deja vacío.
6. Google entrega un **Client ID** y un **Client Secret**. **Van a n8n, no al `.env`**: quien envía
   correos es n8n, no Laravel.

## 2. n8n: credencial de Gmail

Se puede crear desde **Credentials** o **directamente desde el nodo** de Gmail
(_Credential → Create new credential_). Las dos vías guardan lo mismo.

1. Pegar **Client ID** y **Client Secret** → **Sign in with Google**.
2. Aviso _"Google no verificó esta app"_: es la app propia en modo prueba → **Continuar**.
3. Permisos: para enviar basta con **enviar correo**. Durante la configuración se marcaron todos; se
   pueden reducir revocando el acceso en `myaccount.google.com/permissions` y volviendo a conectar.

## 3. Telegram: bot y chat

1. **@BotFather** → `/newbot` (o `/mybots` → bot → **API Token** para ver o revocar uno existente).
2. El token va a `.env` como `TELEGRAM_BOT_TOKEN` y a la credencial **Telegram account** de n8n.
3. **Chat ID:** escribir cualquier mensaje al bot y consultar
   `https://api.telegram.org/bot<TOKEN>/getUpdates` → `result[].message.chat.id`. Va a `.env` como
   `TELEGRAM_CHAT_ID` y al campo _Chat ID_ del nodo (modo **Fixed**: siempre es el mismo chat).

> El primer bot usado era compartido con otra integración (llegaban mensajes ajenos al chat) y su token
> quedó expuesto en los registros de una sesión con otra herramienta (ver `docs/BITACORA.md`). Se creó
> un **bot propio del proyecto**; el `chat_id` no cambia, porque identifica a la persona, no al bot.
> **Pendiente:** revocar el token del bot antiguo, que rompe la otra integración hasta actualizarla allí.

---

## 4. Flujos

### `Reformas · Avisos`: un flujo, tres ramas

Exportado en [`n8n/workflows/reformas-avisos.json`](../n8n/workflows/reformas-avisos.json).
`DELIVERY_TEAM_URL` y `DELIVERY_CUSTOMER_URL` apuntan los dos a `/webhook/reformas-events`
(en Docker, `http://n8n:5678/webhook/reformas-events`).

| Nodo                    | Qué hace                                                                                                                                                                          |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Webhook**             | Puerta única: `POST reformas-events`                                                                                                                                              |
| **Switch** (`Rules`)    | Regla 1: `{{ $json.body.event }}` = `lead.classified` → salida **equipo**. Regla 2: `lead.customer_message_approved` → **cliente**. **Fallback Output: Extra Output** → **extra** |
| **Send a text message** | Telegram, rama `equipo`                                                                                                                                                           |
| **Send a message**      | Gmail, rama `cliente`                                                                                                                                                             |
| **Stop and Error**      | Rama `extra`: _"Evento no soportado por este flujo"_                                                                                                                              |

**Por qué el `Stop and Error` y no ignorar lo desconocido:** sin él, el flujo terminaría bien y n8n
respondería OK; Laravel daría por entregado un aviso que nadie atendió. Con el error, el aviso se
reintenta y acaba **apartado y visible** en el panel de Resiliencia.

**Renombrar las salidas** del Switch (`equipo`, `cliente`) no es cosmético: en el lienzo se lee de un
vistazo qué rama va a cada canal.

El flujo anterior `Reformas · Equipo` quedó **despublicado**, no borrado: conserva su historial de
ejecuciones y se puede volver a publicar si hiciera falta.

### Nodo Webhook

| Campo          | Valor                         | Por qué                                                                                                                                                                                  |
| -------------- | ----------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| HTTP Method    | `POST`                        | El outbox envía por POST                                                                                                                                                                 |
| Path           | `reformas-events`             | Es el final de la URL que llama Laravel                                                                                                                                                  |
| Authentication | `None`                        | n8n solo es accesible en local y en la red del compose. Para desplegar: autenticación por cabecera (`docs/FUTURE.md`)                                                                    |
| **Respond**    | **`When Last Node Finishes`** | Con `Immediately` n8n responde OK **antes** de enviar: si Gmail o Telegram fallan, Laravel marca el aviso como entregado y se pierde. Así, un fallo devuelve error y el outbox reintenta |
| Response Data  | `First Entry JSON`            | A Laravel solo le importa si fue 2xx o no                                                                                                                                                |

### Nodo Gmail — `Send a message`

| Campo                  | Valor                                                                                  |
| ---------------------- | -------------------------------------------------------------------------------------- |
| Credential             | Gmail account (OAuth2)                                                                 |
| Resource / Operation   | Message / Send                                                                         |
| To                     | `{{ $json.body.data.customer.email }}`                                                 |
| Subject                | `Presupuesto para {{ $json.body.data.customer.name }}`                                 |
| Email Type             | **`Text`** — en HTML los saltos de línea del mensaje se ignoran y sale un solo párrafo |
| Message                | `{{ $json.body.data.message }}`                                                        |
| Append n8n Attribution | **Desactivado** — si no, el cliente ve _"This email was sent automatically with n8n"_  |

### Nodo Telegram — `Send a text message`

| Campo                  | Valor                                      |
| ---------------------- | ------------------------------------------ |
| Credential             | Telegram account                           |
| Resource / Operation   | Message / Send Message                     |
| Chat ID                | `TELEGRAM_CHAT_ID`, en modo **Fixed**      |
| Text                   | Expresión de abajo, en modo **Expression** |
| Append n8n Attribution | **Desactivado**                            |

Texto del aviso (validado con el motor de expresiones de n8n contra un aviso real):

```
{{ $json.body.data.status === 'needs_review' ? '⚠️ Lead para revisar a mano' : '🆕 Lead clasificado' }}
Urgencia: {{ ({ low: 'baja', medium: 'media', high: '🔴 ALTA' })[$json.body.data.urgency] ?? 'sin definir' }} · Categoría: {{ ({ kitchen: 'cocina', bathroom: 'baño', full_home: 'vivienda completa', facade: 'fachada', installations: 'instalaciones', other: 'otra' })[$json.body.data.category] ?? 'sin definir' }}

{{ $json.body.data.summary ?? $json.body.data.review_reason }}

Falta: {{ $json.body.data.missing_information?.join(', ') || 'nada' }}
No cuadra: {{ $json.body.data.contradictions?.join(', ') || 'nada' }}

👤 {{ $json.body.data.customer.name }} · {{ $json.body.data.customer.phone }}
```

| Pieza                           | Para qué                                                                                      |
| ------------------------------- | --------------------------------------------------------------------------------------------- |
| `cond ? 'A' : 'B'`              | Título distinto si el lead necesita revisión humana                                           |
| `({ low: 'baja', … })[valor]`   | Tabla de traducción: los códigos del sistema salen en español                                 |
| `valor ?? 'sin definir'`        | Un lead sin clasificar no tiene urgencia ni categoría (`null`)                                |
| `summary ?? review_reason`      | Sin resumen, el motivo de la revisión                                                         |
| `lista?.join(', ') \|\| 'nada'` | Las listas se unen en texto; `?.` evita romper si la lista es `null`; una lista vacía da `''` |

Telegram detecta el teléfono y lo hace clicable (`entities: phone_number` en la respuesta).

---

## 5. Probar sin gastar aprobaciones reales

**URL de prueba y URL de producción son cosas distintas:**

| URL                    | Escucha                                                               | Uso                                   |
| ---------------------- | --------------------------------------------------------------------- | ------------------------------------- |
| `/webhook-test/<path>` | Solo mientras se pulsa **Listen for test event**, y una sola petición | Diseñar: ver los datos y arrastrarlos |
| `/webhook/<path>`      | **Siempre**, con el flujo **publicado** (_Publish_)                   | Uso real. Es la que llama Laravel     |

Por eso **aprobar un borrador en la app no sirve para diseñar**: Laravel llama a la URL de producción,
no a la de prueba. Para diseñar se envía un aviso de ejemplo a la de prueba:

```bash
curl -s -X POST http://localhost:5678/webhook-test/reformas-events \
  -H 'Content-Type: application/json' -d @aviso-de-ejemplo.json
```

Un correo `tu-usuario+algo@gmail.com` llega a la propia bandeja: sirve para probar el envío real sin
molestar a nadie. Los leads de las pruebas E2E usan `@example.com` y **rebotan**.

**Prueba de punta a punta:** registrar un lead en Recepción con un correo `+alias` propio → esperar la
clasificación → aprobar el borrador en Clasificación → llega el correo **sin tocar n8n**.

## 6. Resiliencia comprobada

Mientras `Reformas · Equipo` no existía, los avisos `lead.classified` recibían **404** y quedaban en el
outbox. Al publicar el flujo se entregaron solos, escalonados por el backoff:

- 8 avisos del equipo pendientes → 8 entregados, ninguno perdido.
- El de la prueba necesitó **16 intentos en ~50 minutos** (esperas crecientes hasta 5 min), no miles.
- Mientras tanto, CRM y correo al cliente se entregaron al primer intento: **un circuito por destino**.

Consecuencia esperada: al correr las E2E en local llegan Telegrams de los leads de prueba
("Lucía Fernández"). Se decidió aceptarlo: un lead que entra por Recepción **debe** avisar al equipo, y
en el CI no ocurre porque n8n no tiene flujos.

---

## 7. Trampas encontradas

| Trampa                                                       | Síntoma                                                                                                                 | Solución                                                                             |
| ------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| Laravel apuntando a `/webhook-test/`                         | Solo funciona con el botón pulsado; en uso normal, 404                                                                  | URLs de producción y flujo publicado                                                 |
| `Respond: Immediately`                                       | Un fallo de Gmail o Telegram no se reintenta                                                                            | `When Last Node Finishes`                                                            |
| Copiar expresiones largas desde la terminal                  | La terminal parte líneas **dentro de las comillas**: `[invalid syntax]`, o espacios al inicio de cada línea del mensaje | Copiar al portapapeles del sistema (`xclip -selection clipboard`) o desde un archivo |
| Saltos de línea dentro de un único `{{ }}`                   | El editor marca `[invalid syntax]` aunque el motor lo ejecuta                                                           | Una línea por `{{ }}`, o la expresión en una sola línea                              |
| El editor cierra llaves solo                                 | Al pegar, falta o sobra un `}` al final                                                                                 | Comprobar que termina exactamente en `}}`                                            |
| `Email Type: HTML` con texto plano                           | Todo el correo en un párrafo                                                                                            | `Text`                                                                               |
| Campos que apuntan a una estructura vieja (`customer_email`) | Resultado en rojo, `undefined`                                                                                          | Volver a arrastrar desde el INPUT actual                                             |
| Usuario de prueba no añadido en Google Cloud                 | Google bloquea el inicio de sesión                                                                                      | _Público → Usuarios de prueba_                                                       |

## 8. Verificado de punta a punta

| Prueba                                                          | Resultado                                                                                                                                  |
| --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Lead nuevo por Recepción (`Marta Unificada`, fachada en Bilbao) | Telegram al primer intento, con la incoherencia detectada: _"el tipo marcado era 'other' pero la descripción detalla trabajos de fachada"_ |
| Aprobar un borrador                                             | Correo entregado (`SENT` en la respuesta de Gmail)                                                                                         |
| Evento inventado                                                | HTTP 500, sin Telegram ni correo                                                                                                           |

Para ver qué pasó en cada aviso: pestaña **Executions** del flujo. Muestra por qué rama fue y qué
respondió cada nodo.

## 9. Pendiente

- Revocar el token del bot antiguo (`/mybots` → bot → **API Token** → _Revoke_).
- Nodo de IA en n8n para algo no crítico, con un modelo gratuito (ver `docs/FUTURE.md`).
