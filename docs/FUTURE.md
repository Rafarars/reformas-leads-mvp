# Mejoras diferidas

Ideas que no se construyen todavía, con su porqué. Nada se construye antes de necesitarlo,
pero nada se pierde por no escribirlo.

---

## Extensión `intl` en la imagen

**Qué:** `install-php-extensions intl` en la etapa `base` del Dockerfile.

**Por qué:** `Number::format` de Laravel la exige y dentro del contenedor lanza
`RuntimeException`. Se descubrió porque `php artisan db:show` falla con la base migrada.

**Cuándo:** en cuanto el panel o un mensaje necesiten formatear cifras (presupuestos).

## WhatsApp como canal al cliente

**Qué:** enviar el mensaje de seguimiento por WhatsApp Cloud API tras la aprobación humana.

**Por qué no ahora:** exige cuenta de Meta Business, app de desarrollador y plantillas
revisadas por Meta. Hay número de prueba gratuito para una demo. No usar librerías no
oficiales: violan los términos y pueden bloquear el número.

## Resend y el remitente del correo al cliente

**Qué:** enviar el correo de seguimiento con Resend en vez de con la cuenta de Gmail, y que al
responder el cliente le conteste **al usuario que aprobó el borrador** (`Reply-To`).

**Por qué no ahora:** Resend exige un **dominio propio verificado por DNS**; sin él solo deja
enviar a la dirección de la propia cuenta. Su plan gratuito da 3.000 correos al mes con tope de
100 al día y hasta 3 dominios. Comprar un dominio es el único coste del proyecto, y hoy no lo hay.

**Lo que NO se puede hacer, aunque se pida:** enviar _desde_ el correo del usuario que ha
iniciado sesión (`pepe@gmail.com`). Eso exigiría firmar con los DNS de `gmail.com`: es
suplantación, y SPF y DKIM la mandan a spam o la rechazan. El remitente siempre es un dominio
propio; lo que cambia por usuario es el `Reply-To`.

**Qué sí se puede hacer ya, gratis:** poner `Reply-To` con el correo del usuario en el nodo de
Gmail. Da el comportamiento que se busca sin infraestructura nueva.

## QR para conectar el Telegram desde el móvil

**Qué:** junto al enlace `t.me/<bot>?start=<token>` de Ajustes → Telegram, pintar su QR, para
conectarse escaneando desde el teléfono en vez de copiar el enlace.

**Por qué no ahora:** exige una dependencia nueva solo para eso. El enlace con su botón de copiar
ya resuelve el caso, y en el móvil abre Telegram directamente.

## Que el lead aparezca aunque la IA esté caída

**Qué:** al recibir un lead, dejarlo visible en la bandeja al instante como pendiente de
clasificar, y que la clasificación lo enriquezca después.

**Por qué:** hoy, si el modelo va lento o falla, el lead **no aparece hasta que se agotan los cinco
reintentos**: unos nueve minutos. Contradice la tesis del proyecto, que es no perder ni retrasar un
lead aunque un servicio externo caiga. Se descubrió midiendo la API real (43 s por llamada, y
llamadas sin respuesta a los 60 s) mientras se investigaba por qué fallaban las pruebas end to end.

**Cuidado al hacerlo:** el estado nuevo no puede confundirse con `needs_review`, que significa que la
IA respondió y pide criterio humano.

## Despliegue

**Plan y pasos:** [`docs/DEPLOY.md`](DEPLOY.md), con `compose.prod.yml` ya preparado.

**Por qué cambió el esquema:** la idea era base en Supabase y app y n8n en Render. Hoy Render **no
tiene plan gratuito para trabajadores en segundo plano** (7 $/mes), y sin cola no hay entregas, ni
clasificación, ni outbox: se cae justo lo que el proyecto demuestra. Además la app se duerme a los 15
minutos y la base de Supabase se pausa a los 7 días sin uso.

**Elegido:** una VM gratuita de Oracle Cloud Always Free (ARM, 2 vCPU y 12 GB) con el
`docker-compose` entero. Si la verificación de la tarjeta falla, queda la alternativa sin tarjeta
(Render + Supabase con la cola dentro del servicio web), documentada al final de `DEPLOY.md`.

## Miniaturas y limpieza de adjuntos

**Qué:** generar miniaturas de las fotos para el panel, permitir borrar un adjunto y pasar un antivirus
a lo que se sube.

**Por qué no ahora:** el panel muestra los archivos por nombre y se abren en una pestaña; para la demo
llega. El borrado y el antivirus importan cuando esto reciba archivos de desconocidos en producción.

**Qué haría falta:** un caso de uso `AttachmentRemover` que borre fila y archivo en el mismo orden seguro
(primero la fila, después el archivo), un trabajo en cola para las miniaturas y ClamAV o un servicio
externo para el análisis.

## `verify-clean` con base vacía

**Qué:** que `make verify-clean` también pruebe contra una base recién creada.

**Por qué:** la segunda corrida del CI falló por un healthcheck que exigía tablas; en local
no se vio porque la base ya estaba migrada. Hoy solo lo cubre el CI.

**Qué haría falta:** una base desechable propia para la verificación, sin tocar el volumen de
desarrollo.

## Dos envíos simultáneos de la misma persona

**Qué:** impedir que dos peticiones que llegan en el mismo instante con el mismo teléfono o
email creen dos leads.

**Por qué no ahora:** `LeadRegistrar` busca y luego guarda dentro de una transacción, pero la
búsqueda no bloquea: dos transacciones concurrentes pueden no verse y crear un lead cada una.
Requiere que lleguen a la vez, y la `Idempotency-Key` ya cubre el caso más común (el mismo
emisor reintentando).

**Qué haría falta:** un bloqueo consultivo de PostgreSQL (`pg_advisory_xact_lock`) sobre el
teléfono y el email normalizados antes de buscar, o índices únicos y reintentar ante la violación.

## Limpiar las claves de idempotencia

**Qué:** borrar las filas de `idempotency_keys` con más de 24 horas.

**Por qué:** hoy se guardan para siempre. Un reintento legítimo llega en segundos o minutos; pasado
un día, la clave solo ocupa espacio.

**Qué haría falta:** el trait `MassPrunable` de Laravel en `IdempotencyKey` y `model:prune` en el
planificador. Implica tener el planificador corriendo, que hoy no está en el compose.

## Deduplicar en n8n con la `Idempotency-Key`

**Qué:** que los flujos de n8n recuerden el `Idempotency-Key` de cada aviso y descarten los repetidos.

**Por qué no ahora:** el outbox entrega _al menos una vez_. Si Telegram envía el mensaje pero la respuesta
de n8n se pierde por el camino, el reintento lo vuelve a enviar. Es raro y, para un aviso interno, tolerable.

**Qué haría falta:** un nodo que guarde la clave (Redis o una tabla) antes de enviar y corte el flujo si ya
existe. Para el correo al cliente pesa más que para Telegram.

## Evaluar la clasificación con un conjunto fijo

**Qué:** una batería de leads ficticios con la clasificación esperada, corrida a mano contra el
modelo real, que informe aciertos por categoría y urgencia.

**Por qué:** cambiar el prompt o el modelo (ya pasó: `gemini-2.5-flash` dejó de estar disponible)
puede empeorar la clasificación sin que ninguna prueba lo note, porque el CI no llama a la IA.

**Qué haría falta:** un comando `leads:evaluate-classifier` fuera del CI y un umbral mínimo de
aciertos antes de cambiar `GEMINI_MODEL`.

## Nodo de IA en n8n con un modelo gratuito

**Qué:** usar IA dentro de n8n para algo **no crítico**, por ejemplo un **resumen diario** a las 9:00 por
Telegram (_Schedule Trigger_ → consulta → modelo → Telegram), o redactar el aviso al equipo a partir de
los datos ya validados.

**Por qué no ahora:** la clasificación, que sí decide, vive en Laravel con pruebas y validación. La
cuota gratuita de Gemini (500 peticiones al día con Flash Lite) se reserva para ella.

**Modelos gratuitos investigados** (septiembre de 2026):

| Opción                                      | Modelos gratis                                                   | Límite gratuito                                                                     | Datos                                                                                       | En n8n                                                        |
| ------------------------------------------- | ---------------------------------------------------------------- | ----------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- | ------------------------------------------------------------- |
| **Groq** ✅                                 | `qwen/qwen3.8-27b` (chino), `openai/gpt-oss-120b`, `gpt-oss-20b` | 30/min y 1.000/día por modelo (8K tokens/min), sin tarjeta, límite por organización | No retiene por defecto (registros hasta 30 días por abuso); _Zero Data Retention_ activable | _Groq Chat Model_ (subnodo de _AI Agent_ o _Basic LLM Chain_) |
| **Z.ai / Zhipu** ✅ (modelos)               | `GLM-4.7-Flash`, `GLM-4.5-Flash`, `GLM-4.6V-Flash` (visión)      | Gratis según su tabla de precios; límites no publicados                             | Sin verificar                                                                               | _OpenAI Chat Model_ con _Base URL_ propia                     |
| **OpenRouter** ✅                           | Modelos `:free`                                                  | 20/min y **50/día** (1.000 con 10 $ de créditos)                                    | Cada proveedor aplica su política                                                           | _OpenRouter Chat Model_                                       |
| SiliconFlow (sin verificar)                 | Qwen3-8B, DeepSeek-R1-Distill-Qwen-7B (rotan)                    | Según terceros; oficialmente, 1 $ de crédito inicial                                | Sin verificar                                                                               | _OpenAI Chat Model_ con _Base URL_ propia                     |
| DeepSeek, DashScope, Kimi, MiniMax directos | —                                                                | Sin capa gratuita o piden teléfono chino (sin verificar)                            | —                                                                                           | —                                                             |

✅ = verificado en la documentación oficial del proveedor. Fuentes: `console.groq.com/docs/rate-limits`,
`console.groq.com/docs/your-data`, `docs.z.ai/guides/overview/pricing`, `openrouter.ai/docs/api-reference/limits`.

**Práctica pendiente (flujo aparte, fuera del camino de producción):**

```
Manual Trigger ──► Basic LLM Chain ──► (Telegram, opcional)
                        │ Model
                   Groq Chat Model  (qwen/qwen3.8-27b)
```

Los nodos de IA funcionan distinto a los demás: el modelo es un **subnodo** que se cuelga del nodo
principal, no un paso de la línea. Cambiando el _Manual Trigger_ por un _Schedule Trigger_ sale el
resumen diario. **No** se pone la IA en la rama `extra` del Switch: esa rama existe para fallar, y un
nodo de IA ahí tapa el error y se dispara en cada reintento. Si se quiere IA en el camino real, el sitio
sano es entre el Switch y Telegram, para reescribir el aviso con los datos ya validados.

**Recomendación:** Groq con `qwen/qwen3.8-27b` para practicar; Z.ai `GLM-4.7-Flash` como segunda opción
china. Siempre con datos ficticios. Las API keys van en las credenciales de n8n, no en `.env`.

## Notificación de errores en n8n

**Qué:** un _Error Workflow_ que avise por Telegram cuando un flujo falle.

**Por qué no ahora:** con `Respond: When Last Node Finishes`, un fallo vuelve a Laravel como error; el
outbox reintenta y, si no se recupera, el aviso queda apartado y visible en el panel de Resiliencia.

## Autenticar los webhooks de n8n

**Qué:** `Header Auth` en los nodos Webhook y la cabecera correspondiente desde Laravel.

**Por qué no ahora:** n8n solo escucha en `127.0.0.1` y en la red del compose. Imprescindible al desplegar.

## Borrador del correo en párrafos

**Qué:** pedir a Gemini que separe saludo, cuerpo y despedida con saltos de línea.

**Por qué:** hoy el borrador llega en un solo bloque y el correo sale como un único párrafo.

## Silenciar los avisos de las E2E en local

**Qué:** que `make e2e` despublique el flujo de avisos antes de las pruebas y lo vuelva a publicar al terminar.

**Por qué no ahora:** los Telegrams de prueba no molestan y un lead de Recepción **debe** avisar. Filtrar
por `@example.com` se descartó: sería una regla de pruebas en el código de producción.

## Vídeo de la demo

**Qué:** 2 o 3 minutos de captura de pantalla con voz, enlazado desde el README y compartible en
LinkedIn o en una respuesta a una empresa.

**Por qué:** nadie clona un repositorio para evaluarte. El vídeo enseña en tres minutos que un destino
cae, la cola crece sin perder nada y se drena sola al volver.

**Guion previsto:**

| Tramo     | Qué se ve                                                                 |
| --------- | ------------------------------------------------------------------------- |
| 0:00-0:20 | La pregunta de la entrevista y qué se va a demostrar                      |
| 0:20-0:50 | Registrar un lead y verlo clasificado, con lo que falta y el borrador     |
| 0:50-1:40 | Tumbar el CRM desde el panel, inyectar leads y ver la cola con reintentos |
| 1:40-2:20 | Restablecerlo: la cola se drena sola, sin duplicados                      |
| 2:20-2:50 | Aprobar el borrador: llega el correo y el aviso de Telegram               |

**Qué haría falta:** OBS Studio (pantalla y micrófono) y subirlo a YouTube como oculto.
