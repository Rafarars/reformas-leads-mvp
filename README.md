# Reformas Leads

[![CI](https://github.com/Rafarars/reformas-leads-mvp/actions/workflows/ci.yml/badge.svg)](https://github.com/Rafarars/reformas-leads-mvp/actions/workflows/ci.yml)

Recepción y clasificación de leads de una empresa de reformas, diseñada para **no perder un solo
lead aunque un servicio externo esté caído durante horas**.

Nace de un caso práctico real de entrevista: _"tienes una API con un webhook; ¿cómo evitas perder
información si la API falla durante 30 minutos?"_. Este repositorio es la respuesta construida, no
contada.

## El recorrido de un lead

```
 Formulario del panel · API pública v1 · n8n u otro integrador
                            │
                            ▼
        ┌──────────────── LARAVEL ─────────────────┐
        │  1. Valida y no duplica (teléfono/email)  │
        │  2. Guarda el lead y su aviso en la       │   ← misma transacción: o los dos, o ninguno
        │     MISMA transacción (outbox)            │
        └───────────────────┬───────────────────────┘
                            │ después del commit
      ┌─────────────────────┼──────────────────────────────┐
      ▼                     ▼                              ▼
  destino crm          clasificación con IA           destino customer
  (CRM externo)        (Gemini, en la cola)           tras aprobación humana
      │                     │                              │
      │                     ▼                              │
      │               destino team                         │
      │                     │                              │
      ▼                     ▼                              ▼
  webhook           n8n ──► Telegram                n8n ──► Gmail
```

**Un mensaje del outbox por destino, cada uno con su propia cola de reintentos y su circuit
breaker.** Si Gmail falla, Telegram y el CRM siguen entregando.

## Qué demuestra

- **Ningún aviso se pierde si el destino cae.** El lead y su aviso se guardan juntos en la misma
  transacción; la entrega se reintenta con backoff exponencial y jitter; un circuit breaker deja de
  insistir contra un destino caído; lo que agota su plazo se aparta para reprocesarlo a mano.
  En una caída simulada de 30 minutos: **15 o 16 llamadas** al destino, frente a 1.800 reintentando
  cada segundo. Con un destino de n8n caído de verdad durante ~50 minutos: **8 avisos pendientes,
  8 entregados al restablecerlo, ninguno perdido**.
- **Nada se duplica**, ni por reenvío de la persona (misma persona por teléfono o email) ni por
  reintento del sistema (`Idempotency-Key` al recibir y clave por mensaje al entregar).
- **La IA propone, el dominio valida y una persona decide.** Gemini devuelve un JSON con esquema
  fijo; si la categoría no existe, la confianza es baja, la respuesta no es válida o el modelo no
  responde, el lead pasa a **revisión humana** en vez de inventar una clasificación.
- **Lo que toca al cliente lleva aprobación humana por diseño.** El borrador del correo se guarda
  pendiente; solo al aprobarlo se publica el aviso que n8n envía.
- **Datos mínimos hacia la IA:** a Gemini solo van el tipo, la zona, el presupuesto y la
  descripción. Nunca nombre, email ni teléfono. El prompt trata el contenido del lead como dato,
  nunca como instrucciones, y se probó con un intento de inyección.
- **Falla en cerrado**: la imagen de producción no arranca si la configuración es inválida; el
  limitador y la idempotencia lanzan si no saben qué cliente llama.
- **Cada guarda se ha visto saltar**, no solo el camino feliz.

## La demo, en vivo

En el panel de **Resiliencia** se tumba el CRM simulado, se inyectan leads, la cola crece con sus
próximos reintentos a la vista y se drena sola al restablecerlo, sin duplicados. El panel muestra
**un circuito por destino** y en qué estado está cada aviso.

## Módulos

| Contexto                     | Qué resuelve                                                                        |
| ---------------------------- | ----------------------------------------------------------------------------------- |
| `src/Leads/Lead`             | Recibir un lead, normalizarlo y reconocer a quien ya escribió                       |
| `src/Leads/Classification`   | Clasificar con IA, detectar lo que falta y redactar el borrador para revisión       |
| `src/Leads/Attachment`       | Fotos y planos: subida aparte, disco privado y limpieza si la transacción falla     |
| `src/Delivery/Outbox`        | Entregar a cada destino sin perder nada: reintentos, circuito, apartado y reproceso |
| `src/Integrations/ApiClient` | Clientes de la API pública, con su clave y su origen                                |
| `src/Shared`                 | Errores de dominio, reloj, transacción, bus de eventos                              |

## Stack

| Capa           | Tecnología                                                  |
| -------------- | ----------------------------------------------------------- |
| Backend        | Laravel 13, PHP 8.4, arquitectura hexagonal en `src/`       |
| Frontend       | React 19 + Inertia 3, TypeScript, Tailwind 4                |
| Base de datos  | PostgreSQL 17                                               |
| IA             | Gemini (Flash Lite) con salida de esquema fijo y validación |
| Automatización | n8n 2.38 self-hosted: Telegram y Gmail                      |
| Servidor       | FrankenPHP 1.12                                             |
| Pruebas        | Pest 5 (389), Playwright 1.63 (9), PHPStan nivel 7          |
| CI             | GitHub Actions: gitleaks, calidad, end-to-end               |

## Cómo levantarlo

Requisitos: Docker con Compose, PHP 8.4, Composer y Node 22.

```bash
make setup      # dependencias, .env, claves y compilación
make up         # levanta los servicios y migra
make seed       # usuario de prueba
```

App en `http://127.0.0.1:8010`, editor de n8n en `http://localhost:5678`, CRM simulado en
`http://127.0.0.1:8080/control`.

La clasificación con IA necesita una API key de Google AI Studio en `GEMINI_API_KEY`. **Sin ella el
sistema sigue funcionando**: los leads entran y pasan a revisión humana. Los avisos por Telegram y
Gmail necesitan los flujos de n8n: ver [`docs/N8N.md`](docs/N8N.md).

## Pruebas

```bash
make verify   # todo lo que corre el CI: secretos, calidad, tipos, lint, build y end-to-end
make test     # Pint, PHPStan y Pest
make e2e      # Playwright contra los contenedores
```

| Suite                       | Qué cubre                                                                                                   |
| --------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Pest (389)                  | Dominio y casos de uso sin base de datos; contratos de puerto contra el doble **y** contra PostgreSQL; HTTP |
| Playwright `ui` (5)         | Recepción, clasificación, revisión humana y subida de una foto real                                         |
| Playwright `resilience` (3) | Apaga contenedores de verdad: el CRM cae y se recupera, n8n cae y el panel lo detecta                       |

Las pruebas de resiliencia **se niegan a correr** contra una URL que no sea local, con algún
servicio detenido, o con assets compilados más viejos que el código de React.

## Decisiones y alternativas descartadas

| Decisión                                          | Alternativa descartada                   | Por qué                                                                                                                        |
| ------------------------------------------------- | ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| Un mensaje del outbox **por destino**             | Un único destino que reenvía a los demás | Con un solo mensaje, el fallo de un destino obliga a reintentar todo y duplica los avisos ya entregados                        |
| La IA **en el código**, no en n8n                 | Nodo de IA dentro del flujo              | La clasificación decide prioridades y qué se escribe al cliente: necesita pruebas, validación estricta y minimización de datos |
| Avisar al equipo **después** de clasificar        | Avisar al recibir                        | Un aviso sin prioridad ni resumen no sirve para actuar. El CRM sí recibe al instante: es el registro                           |
| Endpoint **aparte** para fotos y planos           | Subirlas junto al alta del lead          | Subir archivos es lento y puede fallar; el lead no puede depender de eso                                                       |
| Borrado explícito del archivo si falla la fila    | Confiar en la transacción                | El sistema de archivos no tiene rollback: quedarían archivos huérfanos                                                         |
| Laravel dentro de Docker desde el principio       | n8n con `network_mode: host`             | n8n tiene que llamar a la app; la red del host solo funciona en Linux                                                          |
| FrankenPHP                                        | nginx + php-fpm                          | Un solo proceso por contenedor: lo más simple para un contenedor por servicio                                                  |
| `/health` consulta solo la base, no n8n           | Incluir n8n                              | Si n8n cae, la app debe seguir aceptando leads; si no, la plataforma reiniciaría la app por un servicio del que no depende     |
| Cola en base de datos                             | Redis                                    | Las tablas `jobs` y `failed_jobs` son visibles desde el panel: la cola y el apartado se pueden enseñar                         |
| Validar la configuración en el `CMD` de la imagen | Validar en un service provider           | FrankenPHP arranca Laravel por petición: una excepción ahí deja el servidor respondiendo 500                                   |
| PostgreSQL                                        | MySQL                                    | Compatible con un despliegue en Supabase sin migrar                                                                            |

## Documentación

| Archivo                                        | Contenido                                                                |
| ---------------------------------------------- | ------------------------------------------------------------------------ |
| [`docs/spec.md`](docs/spec.md)                 | Problema, alcance, arquitectura, resiliencia, IA y orden de construcción |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Convención hexagonal: estructura, nombres y reglas                       |
| [`docs/N8N.md`](docs/N8N.md)                   | Configuración reproducible de Google Cloud, Telegram y los flujos        |
| [`docs/BOOTSTRAP.md`](docs/BOOTSTRAP.md)       | Bitácora del arranque: cada fase, lo que se encontró y cómo se verificó  |
| [`docs/BITACORA.md`](docs/BITACORA.md)         | Bitácora de la IA y los avisos: decisiones, tropiezos y correcciones     |
| [`docs/DEPLOY.md`](docs/DEPLOY.md)             | Plan de despliegue con coste cero y qué proteger antes de publicar       |
| [`docs/FUTURE.md`](docs/FUTURE.md)             | Mejoras diferidas, con su porqué                                         |
| [`docs/RETOMAR.md`](docs/RETOMAR.md)           | Estado actual y checklist                                                |
