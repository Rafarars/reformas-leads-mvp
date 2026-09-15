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

## Despliegue

**Qué:** base en Supabase, app y n8n en Render con la etapa `prod` del Dockerfile.

**Por qué no ahora:** en los planes gratuitos los servicios se duermen; la cola se detiene
y el primer webhook tarda casi un minuto en despertar. Para el portafolio, un vídeo de la
demo de resiliencia muestra más que un enlace que puede estar dormido.

**Qué haría falta:** un `worker` separado para la cola, `N8N_BASE_URL` y `DB_HOST` reales
(la guarda de configuración ya rechaza `localhost`), y verificar las condiciones vigentes de
cada plan.

## Fotos y planos

**Qué:** carga en un endpoint propio, separada de la creación del lead.

**Diseño ya decidido:** el archivo se registra en la misma transacción que su referencia, y
**el archivo físico se borra explícitamente si la transacción falla**, porque el sistema de
archivos no participa en ella. Límite de tamaño solo en esa ruta.

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

## Flujos de n8n y clasificación con IA

**Qué:** los pasos 6 y 7 de la especificación. Clasificar cada lead con Gemini (tipo, prioridad,
información que falta o contradictoria, resumen) y avisar al equipo por Telegram y Gmail desde n8n.

**Por qué no ahora:** hace falta la API key de Google AI Studio, y n8n recibirá los avisos del
outbox en lugar del CRM simulado, cambiando solo `DELIVERY_WEBHOOK_URL`: la entrega, los reintentos y
el circuito ya están resueltos.

## Autenticar el CRM simulado

**Qué:** proteger `/control` del simulador.

**Por qué no ahora:** solo escucha en `127.0.0.1` y en la red del compose, y no existe fuera de
desarrollo. Si alguna vez se despliega una demo pública, sus controles no pueden quedar abiertos.
