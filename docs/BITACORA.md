# Bitácora — pasos 6 y 7

Continuación de `docs/BOOTSTRAP.md` (fundación y pasos 1 a 5). Qué se decidió, qué falló y cómo se
corrigió, en orden. La configuración reproducible de n8n está en `docs/N8N.md`.

---

## Paso 6 — Clasificación con IA

**Fecha:** 15-sep-2026 · commits `ff34efe`, `eb98b27`

### Diseño

- Módulo `src/Leads/Classification`, con su propia tabla `lead_classifications`: la clasificación
  tiene su ciclo (IA, revisión humana), no son columnas de `leads`.
- **Asíncrona:** `lead.received` → `ClassifyLeadOnLeadReceived` encola `ClassifyLeadJob` después del
  commit. Recibir un lead nunca espera a la IA.
- Puerto `LeadClassifier`; adaptador `GeminiLeadClassifier` con **salida estructurada** (`responseSchema`
  con los enums del dominio), `temperature: 0` y validación posterior de cada campo con value objects.
- **A la IA solo va** el tipo marcado, la zona, el presupuesto y la descripción. Nunca nombre, email ni
  teléfono. El prompt declara que el contenido del lead es un dato, no instrucciones.
- Tres fallos distintos, tres caminos:

| Excepción                          | Causa                                                                    | Resultado                                  |
| ---------------------------------- | ------------------------------------------------------------------------ | ------------------------------------------ |
| `ClassifierUnavailableException`   | Conexión, 408, 429, 5xx                                                  | La cola reintenta (backoff, 5 excepciones) |
| `ClassifierNotConfiguredException` | Sin key, u otro 4xx (key inválida, modelo retirado, parámetro rechazado) | Revisión humana, sin reintentar            |
| `InvalidClassifierOutputException` | Respuesta fuera del contrato                                             | Revisión humana: no se adivina             |

- Confianza por debajo de `CLASSIFICATION_MIN_CONFIDENCE` (0,7) → revisión humana con la propuesta guardada.
- Reintentos agotados → `LeadClassificationFailureRecorder` → revisión humana.
- No se clasifican los contactos repetidos ni los leads de la demo de resiliencia (`source=demo`), para
  no gastar cuota.
- Pantalla **Clasificación**: bandeja para confirmar o corregir.

### Tropiezos

1. **`gemini-2.5-flash` ya no se ofrece a cuentas nuevas** (404 _"no longer available to new users"_).
   Se probaron modelos reales con tres leads ficticios (fuga urgente, lead vago, intento de inyección).
2. **Cuota del tier gratuito.** Los límites de AI Studio: Gemini 3.x Flash **5 por minuto y 20 al día**;
   3.5 Flash Lite y 3.1 Flash Lite **15 por minuto y 500 al día**. El límite interno estaba en 8/min,
   por encima del real de Flash.
3. **Flash Lite rechaza `thinkingBudget`** con 400. Los modelos Gemini 3 usan `thinkingLevel`.
4. **Un 400 se trataba como caída** y se habría reintentado cinco veces sin sentido.

**Corrección** (`eb98b27`): modelo `gemini-3.5-flash-lite`, límite 12/min, `thinkingLevel: minimal`, y
solo 408, 429 y 5xx se reintentan.

Prueba real con Flash Lite: fuga → baño, urgencia alta; "quiero reformar" → confianza 0,2 → revisión;
inyección de instrucciones → la ignoró y clasificó la fachada; lead marcado "cocina" con olor a quemado
en el enchufe → instalaciones, urgencia alta (corrige a la persona).

## Paso 6b — Resumen, faltantes, contradicciones y borrador

**Fecha:** 15-sep-2026 · commit `195424f`

- En **la misma llamada** a Gemini (cuota): `summary`, `missing_information[]`, `contradictions[]` y
  `customer_message`.
- **El modelo no conoce el nombre:** escribe `{nombre}` y el panel lo sustituye antes de editar.
- El borrador queda `pending`. Una persona lo edita y lo aprueba; se guarda **el texto aprobado**, no
  el del modelo, y se publica `lead.customer_message_approved` en el outbox. **Nunca se envía solo.**
- Prueba real: detectó _"3.000 € es irreal para una reforma integral de 120 m²"_ y _"marcó cocina,
  pero es un problema eléctrico"_.

---

## Paso 7 — Avisos con n8n

**Fecha:** 15 a 17-sep-2026 · commits `f113d62`, `c4214ec`

### La pregunta de diseño: ¿avisar antes o después de clasificar?

Rafael cuestionó que el equipo recibiera leads sin clasificar. Respuesta y decisión:

- **El CRM es el registro:** recibe el lead al instante, clasificado o no. Si la IA cae una hora, el
  lead ya existe en el sistema comercial.
- **El aviso al equipo es para actuar:** sale **después** de clasificar (`lead.classified`), o cuando el
  lead queda para revisión humana, para que nunca espere sin que nadie lo sepa.

### Opción A frente a opción B

| Opción | Cómo                                                                   | Problema                                                                                                                               |
| ------ | ---------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| A      | Todo el outbox a n8n, y n8n reenvía al CRM                             | Si cae el CRM, n8n falla, se reintenta **todo** y el equipo recibe el mismo Telegram varias veces. Obliga a meter deduplicación en n8n |
| **B**  | **Un mensaje del outbox por destino**, cada uno con su circuit breaker | —                                                                                                                                      |

**Decisión: B.** Implementación:

- `OutboxMessage` con `destination`. Puerto `DeliveryRouter`, implementado por `ConfigDeliveryRouter`
  desde `config/services.php` → `delivery.destinations`:
  `crm` ← `lead.received`; `team` ← `lead.classified`; `customer` ← `lead.customer_message_approved`.
- `CircuitBreaker` y su almacén, por destino. `WebhookMessageDeliverer` con una URL por destino.
- Evento nuevo `lead.classified`. VO `LeadContact` (nombre, email, teléfono) para los eventos que van a
  n8n. `LeadContactFinder` en aplicación. Generar, apartar y aprobar guardan y publican en la misma
  transacción; la llamada a la IA queda **fuera** de la transacción.
- Panel de resiliencia: un circuito por destino y el destino de cada aviso.
- `app:check-config` valida `DELIVERY_CRM_URL`, `DELIVERY_TEAM_URL` y `DELIVERY_CUSTOMER_URL`.
- Pruebas nuevas: reparto por destino, un evento sin destinos no guarda nada, circuitos aislados (cinco
  fallos del CRM no frenan al equipo), URL por destino. Pest 348.

### La sesión con Antigravity (agy)

La sesión semanal de Claude Code se agotó el 15-sep y el trabajo siguió con **agy** (Gemini 3.1 Pro)
entre el 15 y el 16-sep, conversación `a911caa4`. Al volver, Claude reconstruyó la sesión leyendo el
registro de agy (`~/.gemini/antigravity-cli/brain/<id>/.system_generated/logs/transcript.jsonl`; la
base `.db` guarda lo mismo en protobuf y no hizo falta).

**Lo que agy hizo bien:**

- Guió la configuración de Google Cloud (proyecto, Gmail API, consentimiento, usuarios de prueba,
  cliente OAuth) y la conexión de Gmail en n8n, respondiendo a cada captura.
- Obtuvo el `chat_id` de Telegram con `getUpdates`.
- Montó un primer flujo Webhook → Gmail que envió un correo real.

**Errores de agy y cómo se corrigieron:**

| #   | Error                                                                                                        | Impacto                                                                              | Corrección                                                                                |
| --- | ------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------- |
| 1   | **Escribió el token del bot de Telegram en texto plano** al editar el `.env`; quedó en su registro de sesión | Secreto expuesto fuera del `.env`                                                    | Bot de pruebas: se sigue usando de momento; pendiente crear uno propio y revocar el token |
| 2   | **Aplicó la opción A sin que se decidiera**: apuntó todo el outbox a n8n                                     | El CRM simulado dejó de recibir; la demo de resiliencia y su E2E quedaban rotas      | Opción B (un destino por mensaje) y el CRM vuelve a recibir `lead.received`               |
| 3   | **Configuró Laravel con `/webhook-test/`**                                                                   | Solo funcionaba con _Listen for test event_ pulsado; en uso normal, 404              | URLs de producción `/webhook/…` y flujos publicados                                       |
| 4   | Dejó el Webhook en **`Respond: Immediately`**                                                                | Un fallo de Gmail se habría marcado como entregado y perdido                         | `When Last Node Finishes`                                                                 |
| 5   | El flujo **no distinguía eventos**                                                                           | Los 89 `lead.received` también llegaban al nodo de Gmail, sin email                  | Destinos separados; cada flujo recibe solo su evento                                      |
| 6   | Añadió **getters** a `Lead` y **tres strings sueltos** a `approveDraft`, fuera de la convención del proyecto | Diseño inconsistente con el resto del dominio                                        | Revertido: VO `LeadContact` y `LeadContactFinder`                                         |
| 7   | Dejó **Pint en rojo** (espacios al final de línea en una prueba) y sin commit                                | `make verify` habría fallado                                                         | Reescritura de las pruebas y verificación completa                                        |
| 8   | Indicó **`php artisan serve`, `queue:work` y `localhost:8000`**                                              | Todo corre en Docker en `127.0.0.1:8010`: la app parecía caída                       | Explicado; `make up` levanta todo                                                         |
| 9   | Recomendó **marcar todos los permisos** de Gmail                                                             | Más acceso del necesario                                                             | Anotado: basta con enviar; revocable en la cuenta de Google                               |
| 10  | Afirmó que **no podía ver n8n** en el navegador y luego se corrigió                                          | Confusión                                                                            | —                                                                                         |
| 11  | **Se adelantó a modificar código** cuando Rafael solo preguntaba si vaciaba los campos del nodo              | Rafael tuvo que frenarlo: _"que estas haciendo? te pregunte si limpiaba los campos"_ | —                                                                                         |
| 12  | **No guardó nada en Engram** (su búsqueda devolvía _No memories found_)                                      | Contexto no persistido                                                               | Reconstruido desde su registro y guardado                                                 |
| 13  | Dejó `GMAIL_API_KEY` (el Client ID) en el `.env`                                                             | Variable que no usa nadie                                                            | Pendiente de borrar; la credencial vive en n8n                                            |

**Lección:** una herramienta que continúa el trabajo debe leer primero las decisiones cerradas
(`docs/RETOMAR.md`, `AGENTS.md`) y **proponer antes de cambiar la arquitectura**. Ninguna edición de un
archivo con secretos debe mostrar su contenido.

### Configuración de n8n, guiada

Rafael quiso configurar los nodos **él mismo, paso a paso**, para aprender n8n. No se importaron flujos
ni credenciales por comando. Resultado y trampas en `docs/N8N.md`. Resumen:

- `Reformas · Cliente` (reutilizando el flujo creado con agy): Webhook `reformas-customer`, Gmail con
  expresiones al formato nuevo, tipo `Text`, sin firma de n8n. Prueba de punta a punta: lead con correo
  `+alias` → aprobación en el panel → correo recibido **sin tocar n8n**.
- `Reformas · Equipo`: Webhook `reformas-team` → Telegram con urgencia y categoría traducidas.
- **Resiliencia observada:** 8 avisos del equipo acumulados con 404 se entregaron solos al publicar el
  flujo. El de la prueba, en el intento 16, tras ~50 minutos. CRM y correo, al primer intento.

### Tropiezos del paso 7

1. **Formato:** se editó `docs/FUTURE.md` después de pasar el formateador y el CI falló en _Lint_
   (`c4214ec`). **Antes de commitear, formatear lo último que se tocó**, también la documentación.
2. **E2E puntual:** una prueba de clasificación falló justo después de recrear los contenedores (el
   formulario no mostró el resultado). Repetida: 9/9. Primer acceso tras `make up`.
3. **Copiar desde la terminal rompe el código.** La terminal parte las líneas largas e introduce saltos
   de línea con sangría **dentro de las comillas**: n8n daba `[invalid syntax]` y los mensajes salían con
   un espacio al inicio de cada línea. Se resolvió copiando al portapapeles con `xclip` y validando la
   expresión con el motor real de n8n (`@n8n/tournament` dentro del contenedor).
4. **El editor de expresiones de n8n** no acepta saltos de línea dentro de un único `{{ }}` y cierra
   llaves por su cuenta.

### Unificación en un solo flujo

Rafael preguntó si hacían falta dos flujos. No: un flujo con **Switch** demuestra más manejo de n8n y la
resiliencia no cambia, porque vive en Laravel. Quedó `Reformas · Avisos` (Webhook `reformas-events` →
Switch → Telegram / Gmail / `Stop and Error`), con los dos destinos de Laravel apuntando a la misma URL y
los flujos anteriores despublicados. Detalle y verificación en `docs/N8N.md`.

**Tropiezo propio:** al diagnosticar qué rutas respondían, se enviaron mensajes de prueba al flujo viejo
de Telegram, que no filtraba por evento y mandó dos avisos vacíos al chat. Es el argumento a favor del
Switch y del `Stop and Error`: **un flujo debe rechazar lo que no entiende**.

### Tropiezo: `make secrets` no ve lo que aún no está commiteado

El CI falló en _Secret scan_ con `render.yaml`, después de que la comprobación local pasara. Dos causas:

1. **gitleaks escanea el historial**, así que en local se ejecutó sobre commits anteriores y no vio el
   archivo nuevo. La comprobación va **después** del commit y antes del push.
2. **Falso positivo:** la regla `generic-api-key` interpreta `- key: N8N_DIAGNOSTICS_ENABLED` como un
   secreto tras `key:`. Se añadió `.gitleaks.toml` que conserva todas las reglas por defecto y permite
   solo **nombres en mayúsculas** tras `key:`; cualquier valor real se sigue detectando.

### Decisiones al cierre

- **Los Telegrams de las E2E en local se aceptan** (opción B de tres): filtrar por `@example.com` metería
  una regla de pruebas en producción.
- **IA en n8n, más adelante**, para algo no crítico y con un modelo gratuito para no gastar la cuota de
  Gemini. Investigación en `docs/FUTURE.md`.
