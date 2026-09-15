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
| Pruebas                  | Pest 242 · PHPStan nivel 7 sin errores · Playwright 8                               |
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

### Pendiente

- [ ] **Paso 6 · Clasificación con IA** (Gemini 2.5 Flash)
    - [ ] Rafael saca la API key en aistudio.google.com y **la agrega él al `.env`** (nunca en el chat)
    - [ ] Puerto `LeadClassifier` en el dominio; adaptador de Gemini con salida de esquema validado
    - [ ] Tipo, prioridad, información que falta o contradictoria, resumen y **motivo**
    - [ ] Reintento si la respuesta no valida; si la IA falla, el lead sigue visible sin clasificar
    - [ ] **Solo datos ficticios** (el tier gratuito puede usar los datos para entrenar)
    - [ ] Borrador del mensaje al cliente **en revisión humana**, nunca enviado solo
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
- Automatismos nuevos (Makefile, enganches) y reglas de `AGENTS.md`: **se proponen antes de escribirlos**
