# Arquitectura

Hexagonal (puertos y adaptadores) con rebanado vertical, **conviviendo con la estructura que
ya trae Laravel** en lugar de reemplazarla.

Fuentes: el patrón de `engineering-playbook/patterns/hexagonal-architecture.md` (validado en
`nexora-erp`) y los cursos de Codely sobre arquitectura hexagonal, modelado de dominio y
testing.

---

## La regla de oro

> **En `Domain` y `Application`, cada `use` apunta a código propio.**

Ni Laravel, ni Eloquent, ni HTTP, ni Carbon. Si `Illuminate\` aparece en esas capas, algo se
filtró. **No se deja a la disciplina**: una prueba de arquitectura de Pest lo comprueba.

La dependencia inversa sí es natural: un repositorio de Eloquent **usa** la entidad de
dominio para reconstruirla. Lo prohibido es la flecha hacia fuera.

## Por qué no todo va dentro del contexto

En `nexora-erp` (NestJS) los controladores viven dentro de cada contexto, porque el proyecto
nació de cero y el framework no imponía estructura. **Laravel sí la impone**: `app/Http`,
`app/Models`, las rutas, `make:controller`, Wayfinder generando los tipos de TypeScript desde
los controladores. Pelear contra eso cuesta integración y no aporta nada a un proyecto con
un solo consumidor del núcleo.

El reparto resultante: el núcleo en `src/`, los puntos de entrada donde Laravel los espera.

---

## Estructura

```
app/
├── Http/Controllers/
│   └── Api/V1/Leads/
│       └── LeadPostController.php        adaptador de entrada: UNO POR ACCIÓN
├── Models/User.php                        del starter kit
└── Providers/

src/                                       namespace Reformas\
├── Leads/                                 contexto
│   └── Lead/                              módulo
│       ├── Domain/
│       │   ├── Lead.php                   entidad
│       │   ├── LeadId.php                 value object
│       │   ├── LeadRepository.php         PUERTO
│       │   ├── LeadNotFoundError.php      error de dominio, en la raíz del módulo
│       │   └── Find/LeadFinder.php        servicio de dominio (cuando haga falta)
│       ├── Application/
│       │   └── Receive/
│       │       ├── LeadReceiver.php       caso de uso
│       │       └── LeadReceiverRequest.php
│       └── Infrastructure/
│           └── Persistence/Eloquent/
│               ├── EloquentLeadRepository.php   ADAPTADOR
│               └── LeadModel.php
├── Notifications/                         contexto
│   └── TelegramLink/                      vincular el Telegram de cada usuario (token de un solo uso)
└── Shared/
    ├── Domain/                            DomainError y sus categorías, puertos comunes
    └── Infrastructure/Laravel/            el cableado: qué adaptador resuelve cada puerto

tests/
├── Unit/                                  dominio y aplicación, SIN base de datos
│   └── Leads/Lead/...                     espejo de src/
└── Feature/                               HTTP, contratos de puerto, integración
```

| Qué                                                   | Dónde                                                         |
| ----------------------------------------------------- | ------------------------------------------------------------- |
| Controladores                                         | `app/Http/Controllers`, uno por acción                        |
| Entidades, value objects, puertos, errores de dominio | `src/<Contexto>/<Módulo>/Domain`                              |
| Casos de uso                                          | `src/<Contexto>/<Módulo>/Application/<Acción>`                |
| Modelos de Eloquent y repositorios                    | `src/<Contexto>/<Módulo>/Infrastructure/Persistence/Eloquent` |
| Cableado de puertos                                   | `src/Shared/Infrastructure/Laravel`, un solo proveedor        |
| Migraciones                                           | `database/migrations`                                         |

**`User` se queda en `app/Models`**: es del starter kit y lo usa Fortify.

---

## Arquitectura que grita

Las carpetas de `Application` dicen **qué hace el sistema**: `Receive/`, `Search/`,
`Dispatch/`. Nunca `UseCases/`, `Handlers/` ni `Dtos/`.

## Nombres

| Elemento                     | Clase                                                               |
| ---------------------------- | ------------------------------------------------------------------- |
| Entidad                      | `Lead`                                                              |
| Value object                 | `LeadId`, `PhoneNumber`, `RenovationType`                           |
| Puerto                       | `LeadRepository` (**sin** `Interface` ni `I`)                       |
| Adaptador                    | `EloquentLeadRepository`, `InMemoryLeadRepository` (por tecnología) |
| Caso de uso                  | `LeadReceiver`, `LeadSearcher`                                      |
| DTO de entrada / salida      | `LeadReceiverRequest` / `LeadSearcherResponse`                      |
| Error de dominio             | `LeadNotFoundError` (sufijo **`Error`**)                            |
| Excepción de infraestructura | `N8nUnreachableException` (sufijo **`Exception`**)                  |
| Controlador                  | `LeadPostController`                                                |

**`Error` frente a `Exception`** distingue quién tiene la culpa: un error es una regla de
negocio que dijo que no; una excepción es que el mundo falló. Usar un solo sufijo para todo
borra esa diferencia, que es justo la que decide dónde se captura cada una.

---

## Casos de uso

- `final readonly class`, un único `__invoke()`, nombrados por su acción
- Reciben un DTO de primitivas y devuelven `void` (comandos) o un DTO de respuesta (consultas)
- **Orquestan; no deciden.** Una regla de negocio dentro de un caso de uso es una segunda razón
  para cambiar. Sale a un **servicio de dominio con nombre de negocio**

**Responsabilidad única no se mide en dependencias, sino en razones para cambiar.** En
`nexora-erp`, un creador con siete dependencias y cuatro razones pasó a cuatro dependencias y
una razón, y las pruebas siguieron pasando sin tocar una aserción.

**Y la regla se entiende, no se aplica a ciegas**: allí el autenticador no usa finders a
propósito, porque lanzarían "no encontrado" y filtrarían si el usuario existe.

## Servicios de dominio

- Nacen de la **duplicación** entre casos de uso, no de un diseño por adelantado
- **Se instancian directamente, sin interfaz.** Las interfaces sirven para aislarse de cambios
  de infraestructura (cambiar Postgres, cambiar Telegram por Gmail), no de decisiones propias
- **No se mockean en las pruebas**: así cada caso de uso que los usa ejercita también su camino
  de error

### Finder de dominio y finder de aplicación

|                                               | Recibe        | Devuelve            | Sirve a            |
| --------------------------------------------- | ------------- | ------------------- | ------------------ |
| De dominio (`Domain/Find/LeadFinder`)         | value objects | la entidad, o lanza | otros casos de uso |
| De aplicación (`Application/Find/LeadFinder`) | primitivas    | un DTO              | un controlador     |

---

## Entidades y value objects

- Constructor **privado**. `create()` para una instancia nueva (entrada de negocio);
  `fromPrimitives()` para reconstruir desde la base, sin efectos
- `toPrimitives()` para persistir y para comparar en las pruebas
- Mutaciones con nombre de negocio: `registerSecondContact()`, no `setContactCount()`
- **Nunca llaman al reloj ni generan identificadores**: los reciben de los puertos `Clock` y
  `UuidGenerator`. Así una prueba congela el tiempo

**Value objects: "si construye, es válido".** La validación de formato vive en el constructor
con cláusulas de guarda. Dónde va cada validación:

| Regla                                                       | Dónde                               |
| ----------------------------------------------------------- | ----------------------------------- |
| De un solo campo (formato de teléfono)                      | Constructor del value object        |
| Entre campos (presupuesto coherente con el tipo de reforma) | Entidad                             |
| Que necesita I/O (¿ya existe este lead?)                    | Servicio de dominio con repositorio |

## Repositorios

- La interfaz vive en el dominio; el adaptador, en infraestructura
- **`save()` y `search()`.** `search()` devuelve la entidad o `null`: no encontrar no es
  excepcional. Convertir `null` en error es trabajo del finder de dominio
- **Value objects en las firmas**: `search(LeadId $id)`, nunca `search(string $id)`
- **Sin fugas de infraestructura**: nada de `flush()`, ni nombres que delaten a Eloquent. La
  interfaz se diseña desde lo que necesita el cliente, no copiando el adaptador

## Puertos hacia servicios externos

La interfaz expone **solo lo que tienen en común todas las implementaciones**. Un notificador
recibe el contenido y el tipo de aviso; el canal de Telegram, las credenciales de Gmail o el
formato del asunto viven **dentro del adaptador**. Si la firma del puerto delata al primer
adaptador que se escribió, hay acoplamiento estructural aunque no haya un solo `use`.

---

## Errores

- El dominio lanza errores **por categoría** (`NotFoundError`, `ConflictError`,
  `InvalidArgumentError`), nunca códigos HTTP
- La traducción a HTTP ocurre **en un solo lugar**, en `bootstrap/app.php`
- **Dos mensajes por error**: el interno puede llevar identificadores para los registros; el
  público, sin datos internos, es lo único que sale por HTTP
- La respuesta lleva un **`type` legible** (`lead_not_found`), no un código numérico, para que el
  cliente distinga casos sin parsear el texto
- Lo que no es un error de dominio sale como **500**, sin detalles
- **Una prueba verifica que cada error hereda de su categoría**: uno que herede directamente de
  la base saldría como 500 sin que nadie lo note
- Las excepciones de infraestructura **no se capturan en dominio ni en aplicación**

---

## Pruebas

| Capa                   | Tipo                                                        | ¿Infraestructura? |
| ---------------------- | ----------------------------------------------------------- | ----------------- |
| Dominio                | Unitaria                                                    | **No**            |
| Aplicación             | Unitaria, con los puertos mockeados o en memoria            | **No**            |
| Contrato de puerto     | La misma suite contra el doble en memoria y contra Eloquent | Sí                |
| HTTP                   | Feature                                                     | Sí                |
| Interfaz y resiliencia | Playwright                                                  | Sí                |

**Que dominio y aplicación corran sin base de datos es la verificación de que la arquitectura
está bien hecha.**

- **Se mockean los puertos, no el dominio**: entidades, value objects y servicios de dominio
  corren de verdad
- **Object Mothers** con Faker para construir objetos de dominio válidos con datos aleatorios.
  Si un valor aleatorio rompe una prueba, no es una prueba intermitente: es una regla que
  faltaba cubrir
- Los repositorios en memoria **guardan primitivas**, no la instancia: si guardaran el objeto,
  la prueba y el código compartirían la referencia y pasarían por accidente
- **Cada guarda se ve saltar**: una prueba de arquitectura se valida introduciendo a mano la
  infracción que debe detectar

---

## Infraestructura HTTP sin dominio

La autenticación de clientes, la idempotencia y el límite de peticiones son **HTTP**, no reglas
de negocio de un contexto. Viven en `app/`:

| Pieza                                                  | Dónde                                                                 |
| ------------------------------------------------------ | --------------------------------------------------------------------- |
| `AuthenticateApiClient`                                | `app/Http/Middleware` (llama al caso de uso `ApiClientAuthenticator`) |
| `EnsureIdempotentRequest` y su modelo `IdempotencyKey` | `app/Http/Middleware`, `app/Models`                                   |
| Límite por cliente                                     | `AppServiceProvider::configureRateLimiting()`                         |
| Formato de error de la API                             | `app/Http/ApiErrorResponse.php`                                       |

La autenticación **sí** tiene dominio (claves, hash, clientes desactivados) y por eso su lógica
está en `src/Integrations/ApiClient`; el middleware solo la invoca.

## El orden de los middlewares no es el que se declara

**Laravel reordena los middlewares de una ruta por su lista de prioridades.** `ThrottleRequests`
está en esa lista y un middleware propio no: aunque la ruta declare
`['api.client', 'throttle:api-clients', 'idempotent']`, el límite se evaluaba **antes** de
autenticar, con `api_client_id` vacío, y **todos los clientes compartían el mismo límite**.

`php artisan route:list -v` muestra el orden declarado, no el de ejecución. Se descubrió con la
prueba de que un cliente saturado no afecta a otro, y se confirmó con una sonda dentro del
limitador, que vio `NULL` en las dos peticiones.

Dos defensas:

- **Orden explícito** en `bootstrap/app.php` con `prependToPriorityList` y `appendToPriorityList`
- **Falla en cerrado**: el limitador y la idempotencia lanzan `LogicException` si no hay cliente.
  Verificado quitando el orden: la prueba pasa a responder 500 con ese mensaje en vez de mezclar
  clientes en silencio

---

## Outbox y entrega

Contexto `src/Delivery/Outbox`. La pregunta que responde: _¿cómo no se pierde un aviso si el
sistema externo cae durante minutos?_

```
LeadReceiver ─ transacción ─┬─ guarda el lead
                            └─ EventBus → OutboxEventBus → outbox_messages   (el aviso, junto al lead)
                                                  │ después del commit
                                                  ▼
                             (un mensaje por destino: DeliveryRouter)
                             DeliverOutboxMessageJob → OutboxMessageDeliverer → MessageDeliverer (HTTP)
                                  ▲   release(delay)       │ circuito · reintento · apartado
                                  └────────────────────────┘
                             outbox:relay (cada minuto) re-despacha lo atrasado
```

| Pieza                     | Capa            | Qué decide                                                                         |
| ------------------------- | --------------- | ---------------------------------------------------------------------------------- |
| `RetryPolicy`             | Dominio         | Backoff exponencial con jitter (mitad fija, mitad aleatoria); rendirse a las 24 h  |
| `CircuitBreaker`          | Dominio         | Tras 5 fallos seguidos **del mismo destino**, 30 s sin intentar ni gastar intentos |
| `DeliveryRouter`          | Puerto          | Qué destinos reciben cada evento (`ConfigDeliveryRouter`, desde la configuración)  |
| `OutboxMessageDeliverer`  | Aplicación      | Salta lo entregado, no adelanta un reintento, registra el resultado                |
| `MessageDeliverer`        | Puerto          | **Devuelve** el resultado: una caída es esperable, no una excepción                |
| `DeliveryQueue`           | Puerto          | Encolar después del commit                                                         |
| `DeliverOutboxMessageJob` | Infraestructura | Sin límite de intentos de la cola: rendirse lo decide `RetryPolicy`                |

**Un mensaje y un circuito por destino.** Cada evento se guarda una vez por destino suscrito, así
cada entrega se reintenta y cae por separado:

| Destino    | Evento                           | Llega a                           |
| ---------- | -------------------------------- | --------------------------------- |
| `crm`      | `lead.received`                  | CRM simulado (registro inmediato) |
| `team`     | `lead.classified`                | n8n → Telegram                    |
| `customer` | `lead.customer_message_approved` | n8n → Gmail                       |

Si Gmail cae, Telegram y el CRM siguen recibiendo. La alternativa descartada (todo a n8n y que n8n
reenvíe) hacía reintentar todo ante el fallo de un solo destino y duplicaba avisos. Detalle en
`docs/BITACORA.md`.

**El abanico: un mensaje por destinatario.** Un destino puede repartir por persona en vez de tener un
único receptor. Se marca en la configuración (`'fan_out' => true` en `config/services.php`), no en el
código, y hoy solo lo usa `team`: `OutboxEventBus` pide los destinatarios al puerto
`NotificationRecipients` y crea **un `OutboxMessage` por cada `chat_id`**, con el chat dentro del cuerpo.

| Decisión                                                           | Por qué                                                                                                |
| ------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------ |
| Un mensaje **por destinatario**, no una lista dentro de un mensaje | Un reintento con lista dentro reenvía a todos y **duplica avisos**; así solo se reintenta el que falló |
| El adaptador es quien conoce `Notifications`                       | El dominio de `Delivery` no sabe que existe Telegram; la dependencia vive solo en la infraestructura   |
| **Sin destinatarios, ningún mensaje** — pero visible               | El panel de Resiliencia muestra cuántos hay conectados y avisa cuando son 0: nunca en silencio         |

**Fallo permanente: a la DLQ, sin tocar el circuito.** Si alguien bloquea el bot, ese destinatario
falla siempre. Con el circuito por destino, ese usuario roto dejaría **sin avisos a todo el equipo**.
Por eso `DeliveryResult` tiene un tercer estado, `failedPermanently`: el mensaje se aparta con su motivo
y **no cuenta para el circuito**.

Qué es permanente lo decide `WebhookMessageDeliverer`, y **solo el HTTP 422** lo es: es el código con el
que n8n responde cuando Telegram dice que el chat no existe o que bloqueó al bot. Un **404 sigue siendo
reintentable**, porque significa que el flujo de n8n aún no está publicado; se demostró en su día que
esos avisos se entregan solos en cuanto alguien lo publica.

**Un reintento manual abre una ventana nueva.** El plazo de 24 h se cuenta desde el último
reintento manual; si se contara desde la creación, un mensaje viejo reprocesado a mano se volvería a
apartar al primer fallo.

## Clasificación con IA

Contexto `src/Leads/Classification`. La IA **propone**; el dominio **valida** y una persona decide cuando
hace falta.

```
lead.received ─► ClassifyLeadOnLeadReceived ─► ClassifyLeadJob (cola, límite por minuto)
                                                   │
                                   LeadClassificationGenerator
                                   ├─ LeadClassifier (puerto) ─► GeminiLeadClassifier
                                   │     solo tipo, zona, presupuesto y descripción; salida con esquema fijo
                                   ├─ válida y confianza ≥ umbral ─► classified
                                   ├─ dudosa, inválida o sin modelo ─► needs_review
                                   └─ caída ─► excepción: la cola reintenta; agotado ─► needs_review
                                   guarda + publica lead.classified (misma transacción)
```

| Pieza                          | Qué decide                                                                                   |
| ------------------------------ | -------------------------------------------------------------------------------------------- |
| `LeadClassification`           | Estados `classified` / `needs_review` / `reviewed`; borrador `pending` / `approved`          |
| `LeadClassifier`               | Puerto. Distingue caída (reintentar), no configurado (revisión) y salida inválida (revisión) |
| `LeadContact`                  | A quién avisar o escribir. Viaja a n8n en los eventos, nunca a la IA                         |
| `CustomerMessageDraftApprover` | Guarda el texto aprobado por la persona y publica `lead.customer_message_approved`           |

La llamada al modelo queda **fuera** de la transacción: no se retiene la base mientras responde.

## Fotos y planos

Contexto `src/Leads/Attachment`. La pregunta que responde: _¿cómo se suben archivos sin que un fallo deje
basura o agujeros?_

```
POST /api/v1/leads/{lead}/attachments   (o desde el panel, con sesión)
        │  valida tipo, tamaño y nombre ANTES de tocar el disco
        ▼
   AttachmentStorage->write(ruta, contenido)      ← el disco no participa en la transacción
        │
        ├─ transacción: guardar la fila ──► ok
        └─ falla ──► storage->delete(ruta) y se propaga el error
```

| Decisión                                              | Por qué                                                                                   |
| ----------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| Endpoint **aparte** del alta del lead                 | Subir una foto es lento y puede fallar; el lead ya existe y no se pierde                  |
| La **ruta la calcula el dominio** con identificadores | `leads/<lead>/<adjunto>.<ext>`. Con el nombre recibido, un `../` escaparía del directorio |
| **Lista blanca** de formatos (JPG, PNG, WEBP, PDF)    | Con lista negra siempre falta algo. SVG queda fuera: puede llevar scripts                 |
| Extensión según el **tipo declarado**, no el nombre   | `plano.jpg` que es un PDF se guarda como `.pdf`                                           |
| **Disco privado** y descarga por una ruta con sesión  | Los archivos no se sirven solos desde el navegador                                        |
| **Borrado explícito** si la transacción falla         | El sistema de archivos no tiene rollback: sin esto quedan archivos huérfanos              |
| Límite de **10 MB** en el dominio y en la validación  | Rechazar pronto, y que la regla viva en el dominio aunque cambie la puerta de entrada     |

## Vincular Telegram con cada usuario

Contexto `src/Notifications/TelegramLink`. La pregunta que responde: _¿cómo sabe el sistema que el
Telegram que pulsó **Start** es el de la persona que tiene la sesión abierta?_

Telegram da un `chat_id`; la aplicación tiene una sesión. Nada los une, y un bot **no puede escribir
primero** (regla antispam: la persona tiene que iniciar la conversación). Lo resuelve un token de un solo
uso, que solo pudo salir de una sesión ya autenticada:

```
Panel con sesión  ─► RequestLink ─► token en claro (64 hex) ─► t.me/<bot>?start=<token>
                         │                                            │
                  en la base: SOLO el hash                     Telegram entrega /start <token>
                                                                      │
                                                     CompleteLink ◄────┘  ata chat_id ↔ usuario
                                                     y quema el token (token_used_at)
```

| Decisión                                                    | Por qué                                                                                    |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| La identidad la aporta **el token**, no Telegram            | Telegram solo añade el `chat_id`; quién es esa persona ya lo dijo la sesión                |
| En la base vive **solo el hash** del token                  | Igual que la API key por origen: quien lea la base no puede suplantar un vínculo pendiente |
| Token de **64 caracteres hex**, sin prefijo                 | Es el máximo exacto que admite el payload de `/start`; un prefijo ya no cabría             |
| **Caduca a los 15 minutos** y se quema al usarse            | Un enlace que caiga en un grupo o en un registro no sirve al día siguiente                 |
| `chat_id` con índice **único**, y guarda en el caso de uso  | Sin esto, dos cuentas con el mismo Telegram recibirían **cada lead por duplicado**         |
| Al renovar el token **se conserva el `chat_id` anterior**   | Quien ya estaba conectado sigue recibiendo avisos durante los 15 minutos de la renovación  |
| `ListRecipients` devuelve solo los **vínculos completados** | Un token pendiente no es un destinatario                                                   |

**La puerta: Ajustes → Telegram.** Tres controladores, uno por acción, sobre los casos de uso que ya
existían. El enlace lo pulsa la persona desde su sesión; el bot confirma llamando a
`POST /api/v1/telegram/link`, dentro del mismo grupo con **API key por origen**, límite e idempotencia
que usa el alta de leads.

**El token en claro no se guarda en ningún sitio.** Viaja **una sola vez**, como dato flash de la
redirección, y la pantalla lo mantiene en memoria. Si se recarga, desaparece: se vuelve a pedir, y eso
renueva el token sin perder el chat ya vinculado. Lo que **sí** sobrevive a la recarga es el estado
("pendiente", "conectado desde"), porque sale de la entidad, no de la sesión. Queda un residuo
inevitable: el dato flash vive en la sesión **durante una petición**, segundos, no los 15 minutos que
duraría si se guardara. Es el precio de redirigir después de un POST.

**El nombre del bot es configuración** (`TELEGRAM_BOT_USERNAME`), nunca un valor escrito en el código: si
falta, la pantalla lo dice en vez de pintar un enlace que no lleva a ninguna parte.

## Herramientas de la demo

El CRM simulado (`docker/fake-crm`), su cliente (`app/Services/FakeCrmClient`) y los leads de
demostración (`app/Support/DemoLeads`) **no son producto**: viven fuera de `src/` y el dominio no los
conoce. Los leads de demostración son siempre ficticios y llevan el origen `demo`.

La **IA simulada** (`docker/fake-gemini`) sigue el mismo patrón: responde al instante con la forma que
espera `GeminiLeadClassifier`. En local y en el CI se usa ella, porque una prueba tiene que comprobar
este sistema y no la latencia de Google: las corridas dependían de que la API externa fuese rápida (se
midieron 43 s por llamada, y llamadas sin respuesta a los 60 s) y **gastaban la cuota gratuita diaria**,
porque cada reintento del trabajo es otra petición. El interruptor está en `docker-compose.yml`:

```yaml
GEMINI_BASE_URL: ${GEMINI_BASE_URL:-http://fake-gemini:8080/v1beta}
```

Por defecto, la simulada. Para usar la real en local basta con definir `GEMINI_BASE_URL` y la clave
verdadera en el `.env` y levantar de nuevo. **Producción llama siempre a Google**: `render.yaml` no
define esa variable, así que rige el valor por defecto de `config/services.php`.
