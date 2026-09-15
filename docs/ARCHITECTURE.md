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
                             DeliverOutboxMessageJob → OutboxMessageDeliverer → MessageDeliverer (HTTP)
                                  ▲   release(delay)       │ circuito · reintento · apartado
                                  └────────────────────────┘
                             outbox:relay (cada minuto) re-despacha lo atrasado
```

| Pieza                     | Capa            | Qué decide                                                                        |
| ------------------------- | --------------- | --------------------------------------------------------------------------------- |
| `RetryPolicy`             | Dominio         | Backoff exponencial con jitter (mitad fija, mitad aleatoria); rendirse a las 24 h |
| `CircuitBreaker`          | Dominio         | Tras 5 fallos seguidos, 30 s sin intentar y sin gastar intentos                   |
| `OutboxMessageDeliverer`  | Aplicación      | Salta lo entregado, no adelanta un reintento, registra el resultado               |
| `MessageDeliverer`        | Puerto          | **Devuelve** el resultado: una caída es esperable, no una excepción               |
| `DeliveryQueue`           | Puerto          | Encolar después del commit                                                        |
| `DeliverOutboxMessageJob` | Infraestructura | Sin límite de intentos de la cola: rendirse lo decide `RetryPolicy`               |

**Un reintento manual abre una ventana nueva.** El plazo de 24 h se cuenta desde el último
reintento manual; si se contara desde la creación, un mensaje viejo reprocesado a mano se volvería a
apartar al primer fallo.

## Herramientas de la demo

El CRM simulado (`docker/fake-crm`), su cliente (`app/Services/FakeCrmClient`) y los leads de
demostración (`app/Support/DemoLeads`) **no son producto**: viven fuera de `src/` y el dominio no los
conoce. Los leads de demostración son siempre ficticios y llevan el origen `demo`.
