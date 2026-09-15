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
| Pruebas                  | Pest 313 · PHPStan nivel 7 sin errores · Playwright 9                               |
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

### Pendiente

- [ ] **Paso 6b · Ampliar la clasificación** (quedó fuera del primer corte)
    - [ ] Información que falta o contradictoria y un resumen del proyecto
    - [ ] Borrador del mensaje al cliente **en revisión humana**, nunca enviado solo (encaja con n8n)
- [ ] **Paso 7 · Flujos de n8n**
    - [ ] Workflow que recibe el aviso del outbox (cambiar `DELIVERY_WEBHOOK_URL` a n8n)
    - [ ] Aviso al equipo por **Telegram** (token de bot)
    - [ ] Resumen por **Gmail** (OAuth2)
    - [ ] Exportar los workflows como JSON al repositorio
- [ ] **Paso 8 · Fotos y planos** (endpoint propio, misma transacción, borrar el archivo huérfano)
- [ ] **Cierre**
    - [ ] Actualizar README con la demo y, si procede, un vídeo corto
    - [ ] Extraer al playbook lo validado en los pasos 6 a 8

### Deuda anotada en `docs/FUTURE.md`

- [ ] Extensión `intl` en la imagen (`Number::format` falla en el contenedor)
- [ ] Dos envíos simultáneos de la misma persona (bloqueo consultivo de PostgreSQL)
- [ ] Limpiar `idempotency_keys` con más de 24 h
- [ ] `make verify-clean` contra una base vacía
- [ ] Autenticar `/control` del CRM simulado si alguna vez se despliega
- [ ] Despliegue (Supabase + Render) y WhatsApp como canal al cliente

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
- Automatismos nuevos (Makefile, enganches) y reglas de `AGENTS.md`: **se proponen antes de escribirlos**
