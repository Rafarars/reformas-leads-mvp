# Plan de trabajo en curso

Plan **ejecutable** del siguiente tramo, escrito para que lo pueda seguir alguien (o algo) que no haya
estado en la conversación donde se decidió. Cada fase dice **qué**, **por qué** y **cómo se comprueba**.

Antes de tocar nada: `AGENTS.md` (reglas), `docs/ARCHITECTURE.md` (convención hexagonal) y
`docs/RETOMAR.md` (estado). Lo de aquí **no** deroga nada de eso.

**Orden:** las fases van en orden y **no se adelantan**. La Fase 0 existe para que las fases siguientes se
puedan verificar; saltársela deja sin red todo lo demás.

---

## Fase 0 · Despejar el terreno

### 0.1 El enlace muerto del registro

`resources/js/pages/auth/login.tsx:97` pintaba siempre un enlace **"Sign up"** hacia `register()`. Esa ruta
**no existe en producción**: `config/fortify.php` solo activa `Features::registration()` si
`REGISTRATION_ENABLED` o si el entorno no es producción.

**Cómo se resolvió:** se hizo condicional a `canRegister`, prop expuesta por `AuthenticatedSessionController`
según las características activas de Fortify. Verificado en ambos sentidos (con registro activo y desactivado)
con pruebas de Feature y E2E.

### 0.2 El enlace muerto de la contraseña

`Features::resetPasswords()` estaba activo por defecto, mostrando **"Forgot your password?"** en el login.
Pero `render.yaml` **no define variables `MAIL_*`**, por lo que en producción el correo de recuperación
no saldría a ninguna parte.

**Cómo se resolvió:** en `config/fortify.php` se condicionó `Features::resetPasswords()` a que exista un
mailer real configurado (`config('mail.default') !== 'log' && config('mail.mailers.smtp.host') !== null && !app()->isProduction()`).
En producción la característica queda inactiva, Fortify no registra la ruta y la prop `canResetPassword` llega
como `false`, ocultando el enlace de forma limpia sin tocar `login.tsx`.

### 0.3 La prueba inestable

`e2e/tests/ui/reception.spec.ts` y `e2e/tests/ui/classification.spec.ts` fallaban de forma intermitente. El
registro de Playwright decía que el clic lo recibía `<html class="nprogress-busy">`, y en el reintento que
el elemento quedaba fuera de la ventana.

**Prohibido arreglarlo con `force: true`.** Razones:

1. `force: true` **salta las comprobaciones de accionabilidad** (visible, habilitado, estable y **no
   tapado**). La prueba deja de comprobar que una persona puede pulsar ahí, pero sigue saliendo verde:
   miente. Es exactamente lo que `AGENTS.md` prohíbe cuando dice que una guarda que salta de más se acaba
   desactivando.
2. También salta el "desplazar hasta que se vea", y uno de los fallos era justo
   `element is outside of the viewport`. Forzar un clic sobre algo que no está en pantalla no lo arregla:
   lo vuelve impredecible.
3. **La prueba no miente: señala defectos reales** de la aplicación y de la infraestructura de pruebas.

**Nota de verificación y falsación:**
La hipótesis inicial de que `usePoll(3000)` desmontaba el portal del `Select` de Radix UI cerrándolo
automáticamente a los 3 segundos resultó **falsa** tras comprobarse empíricamente contra `d74fba1`: la prueba
con espera de 4 s pasaba tanto con como sin pausa del sondeo. La prueba artificial se descartó y la pausa de
sondeo en `classification/index.tsx` se eliminó para mantener el código simple.

**Causas reales y cómo se resolvieron:**

- **Interferencia de `nprogress-busy`:** Cada 3 segundos `usePoll` disparaba la barra de progreso global
  de NProgress, añadiendo la clase `.nprogress-busy` a `<html>` y bloqueando los clics. Se resolvió
  configurando `showProgress: false` y recarga parcial `only: ['classifications']` en
  `resources/js/pages/classification/index.tsx` y en `resources/js/pages/resilience/index.tsx`.
- **Bloqueo de scroll y posicionamiento del desplegable de Radix UI:**
    1. Al elegir una opción, Radix UI mantiene el bloqueo de scroll del `<body>` hasta que concluye la animación de cierre. Si el siguiente clic se lanza antes, el elemento destino no es interactivo. Se resolvió en el page object esperando explícitamente a que la opción anterior desaparezca del DOM (`await expect(option).not.toBeVisible()`) antes de continuar con la siguiente interacción.
    2. El menú de un `Select` de Radix se renderiza en un portal y se despliega hacia arriba o hacia abajo según el espacio disponible. Si la tarjeta del lead está cerca del borde inferior de la ventana, el auto-scroll de Playwright solo asoma el trigger en el límite inferior del viewport, lo que provoca que el menú del portal se dibuje fuera de la pantalla (`element is outside of the viewport`). Por ello es necesario `scrollIntoViewIfNeeded()` centrando el elemento (`block: 'center'`) en `ClassificationPage`: garantiza margen suficiente arriba y abajo para que el portal del desplegable quede completamente dentro de la ventana gráfica.
- **Colisión de sesiones entre workers de Playwright:** Todos los workers compartían el mismo `STORAGE_STATE`
  (`test@example.com`), consumiendo los mensajes flash de sesión única de Laravel unos antes que otros y
  activando el rate limiter de Fortify (429 Too Many Requests). Se resolvió aislando la autenticación por
  worker (`testInfo.parallelIndex`) con credenciales independientes (`test+N@example.com`).
- **Aislamiento y limpieza hermética de datos:** Los leads creados por las pruebas E2E usan el dominio
  `@e2e.test`. El comando `php artisan e2e:purge-leads`, invocado en `e2e/global-setup.ts` con `stdio: 'inherit'`,
  purga en cada ejecución los leads de `@e2e.test` y los de `source = 'demo'` junto con sus mensajes de outbox
  y adjuntos en disco, preservando intactos los leads reales de desarrollo sin recurrir a `migrate:fresh`.
- **Guarda de arquitectura en pruebas:** Se impide la importación directa de `test` desde `@playwright/test`
  en `e2e/tests/**/*.spec.ts` mediante la regla `no-restricted-imports` de Oxlint configurada en `vite.config.ts`.

---

## Fase 1 · Avisos de Telegram por usuario · dominio

**Qué resuelve.** Hoy el aviso de Telegram va a un `chatId` fijo escrito en el nodo. Quien entra al sistema
no se entera de nada si no es esa persona. Y para enseñarlo, un reclutador no tiene forma de ver el bot
funcionando: pulsa _Start_ y no pasa nada, porque **no hay ningún disparador escuchando** (el único
disparador del flujo es el `Webhook` que llama Laravel).

**El problema de identidad.** Telegram da un `chat_id`; la aplicación tiene una sesión. Nada los une, y un
bot **no puede iniciar una conversación** (regla antispam de Telegram: la persona escribe primero). Se
resuelve con un **token de un solo uso**:

```
Panel (con sesión iniciada)
  └─ "Conectar mi Telegram" → Laravel genera un token que caduca
       └─ enlace  t.me/<bot>?start=<token>          (el payload de /start admite 64 caracteres)
            └─ Telegram entrega al bot  /start <token>
                 └─ n8n (Telegram Trigger) → POST /api/v1/telegram/link {token, chat_id}
                      └─ Laravel ata chat_id ↔ usuario y quema el token
```

La identidad **no la aporta Telegram**: la aporta el token, que solo pudo salir de una sesión ya
autenticada. Telegram solo añade el `chat_id`.

**Módulo nuevo** `src/Notifications/TelegramLink/`, con la convención de `docs/ARCHITECTURE.md`:

| Capa             | Piezas                                                                                                                                                                                                           |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Domain`         | `TelegramChatId` y `LinkToken` (VO, validan en el constructor), `TelegramLink` (entidad), puertos `TelegramLinkRepository` y `LinkTokenGenerator`, errores `LinkTokenExpiredError` y `LinkTokenAlreadyUsedError` |
| `Application`    | `RequestLink`, `CompleteLink`, `ForgetLink`, `ListRecipients` — `final readonly` con un solo `__invoke`                                                                                                          |
| `Infrastructure` | Repositorio de Eloquent, modelo y migración `telegram_links`                                                                                                                                                     |

**Reglas que no se negocian:** `Domain` y `Application` sin Laravel, sin Eloquent y sin Carbon (hay una
prueba de arquitectura que lo comprueba). El token **se guarda con hash**, como la API key por origen:
quien lea la base no puede suplantar un vínculo pendiente.

**Se comprueba:** dominio sin base de datos; el contrato del puerto **contra el doble en memoria y contra
Eloquent**, como el resto del proyecto. Cada guarda en los dos sentidos: token caducado rechaza, token
válido acepta; token ya usado rechaza, token nuevo acepta.

---

## Fase 2 · El abanico de entrega · **la decisión de diseño**

Hoy `lead.classified` genera **un** mensaje de outbox para el destino `team`
(`src/Delivery/Outbox/Infrastructure/OutboxEventBus.php`). Con varios destinatarios hay dos caminos:

|                 | Un mensaje con la lista dentro   | **Un mensaje por destinatario**  |
| --------------- | -------------------------------- | -------------------------------- |
| Reintento       | Reenvía a todos → **duplicados** | Reintenta solo al que falló      |
| Circuit breaker | Por destino                      | Por destino, **sin tocar nada**  |
| DLQ             | Todo o nada                      | El fallo se aísla en una persona |

**Se hace lo segundo.** `OutboxMessage` ya admite varios mensajes con el mismo destino y el circuito se
lleva **por destino**, así que el abanico **no toca el núcleo de resiliencia**, que es lo que el proyecto
demuestra.

**Cómo:**

- Puerto `NotificationRecipients` con `chatIdsFor(string $destination)`.
- En `OutboxEventBus::publish`, para `team`: un `OutboxMessage` por `chat_id`, con el `chat_id` en el
  cuerpo.
- **Sin destinatarios, ni un mensaje** — y que se vea en el panel de Resiliencia, nunca en silencio.

**El fallo que hay que prever.** Si alguien **bloquea el bot**, Telegram responde 403 en cada intento. Con
el circuito por destino, ese usuario roto **abriría el circuito para todo el equipo** y nadie recibiría
nada. Hay que tratar 403 y _chat not found_ como **fallo permanente**: a la DLQ directamente, **sin contar
para el circuito**. Es el mismo criterio que ya usa el clasificador (4xx≠408/429 → revisión humana).

**Se comprueba:** con dos destinatarios y uno que falla de forma permanente, el otro recibe, el circuito
**no** se abre y el fallido acaba en la DLQ.

---

## Fase 3 · Pantalla para conectarse

En Ajustes: **Conectar Telegram** (muestra el enlace y un QR), **Desconectar**, y el estado (conectado
desde cuándo). `data-test` sembrados al escribir el componente, y Page Object: ningún `.spec.ts` conoce un
selector.

---

## Fase 4 · n8n

**Segundo flujo publicado:** `Telegram Trigger` (evento _message_) → filtra los que empiezan por `/start `
→ `HTTP Request` a `POST /api/v1/telegram/link` con la API key por origen → responde a la persona
_"Listo, ya recibirás los avisos"_.

En el flujo de avisos, el nodo de Telegram pasa de `chatId` **Fixed** a una **expresión** que lee el del
cuerpo del mensaje.

**Caveat del plan gratuito:** con Render, si n8n está dormido el webhook de Telegram falla y el `/start`
tarda o se pierde. Telegram reintenta, pero aquí **el ping de cron-job.org deja de ser opcional**.

**Los flujos se exportan a `n8n/workflows/` sin secretos**, con el `chat_id` sustituido por su marcador,
como ya se hace.

---

## Fase 5 · La cuenta de demostración

Para que un reclutador vea el sistema sin que le pidan cuenta: usuario de demostración con las
credenciales en el README.

**El panel no distingue roles: quien entra, puede todo.** Lo único peligroso es aprobar un mensaje, porque
**sale un correo real desde el Gmail de la empresa**. Así que hace falta el mínimo imprescindible: una
marca en el usuario y **una guarda en el caso de uso que aprueba** — en el caso de uso, **no** en el
controlador.

Con eso, el reclutador entra, conecta su Telegram, registra un lead y **ve el aviso llegarle al móvil**.

---

## Fase 6 · Documentación

`ARCHITECTURE.md` (el módulo nuevo), `N8N.md` (el flujo de vínculo y el `chatId` por expresión), `USO.md`
(cómo se conecta una persona), `DEPLOY.md` (lo que cambia en producción), `RETOMAR.md` (estado) y
`FUTURE.md` (lo que quede fuera, con su porqué).

Las decisiones con su porqué van **además** a Engram, proyecto `reformas-leads-mvp`.

---

## Fuera de este plan

Anotado para no perderlo, pero **no se hace aquí**:

- **Foto en el formulario de Recepción.** Hoy solo se adjunta desde Clasificación, y la API pública lo hace
  en dos pasos (`POST /api/v1/leads` y luego `POST /api/v1/leads/{lead}/attachments`). Al llevarlo al
  formulario hay que decidir **la frontera de la transacción**: si falla el adjunto, ¿se queda el lead?
  El borrado del huérfano ya está resuelto en `AttachmentStorage`; la decisión no.
  **Cuidado:** si el aviso ha de decir "1 foto adjunta", el contador va en **`lead.classified`**, que es el
  que alimenta a `team` → Telegram. En `lead.received` **no llegaría**, porque ese va a `crm`.
- Miniatura de las fotos en el panel.
- Borrador del correo en párrafos (comprobar si ya salía bien o fue casualidad).
- Revocar el token del bot antiguo de Telegram.
- Ping en cron-job.org y borrar el servicio viejo `n8n` de Render.
