# Reformas Leads

[![CI](https://github.com/Rafarars/reformas-leads-mvp/actions/workflows/ci.yml/badge.svg)](https://github.com/Rafarars/reformas-leads-mvp/actions/workflows/ci.yml)

Recepción y clasificación de leads de una empresa de reformas, diseñada para **no perder
un solo lead aunque un servicio externo esté caído durante minutos**.

Nace de un caso práctico real de entrevista: _"tienes una API con un webhook; ¿cómo evitas
perder información si la API falla durante 30 minutos?"_. Este repositorio es la respuesta
construida, no contada.

## Estado

| Hito                                                                          | Estado    |
| ----------------------------------------------------------------------------- | --------- |
| Fundación: Docker, salud, suite de pruebas, CI, configuración validada        | **Hecho** |
| API pública `v1` de ingesta (API key por origen, idempotencia, rate limiting) | **Hecho** |
| Outbox, reintentos con backoff, circuit breaker y DLQ                         | **Hecho** |
| Clasificación con IA y flujos de n8n (Telegram, Gmail)                        | Pendiente |
| Panel de resiliencia con simulación de caídas                                 | **Hecho** |

La especificación completa está en [`docs/spec.md`](docs/spec.md).

## Qué demuestra hoy

- **Cada servicio en su contenedor**, orquestados con Docker Compose en una red compartida y
  comunicándose por nombre de servicio.
- **Un endpoint de salud que dice la verdad**: consulta la base, responde 503 si no está, y se
  recupera solo cuando vuelve.
- **El panel detecta la caída de n8n** sin dejar de funcionar, y una prueba de Playwright lo
  verifica apagando el contenedor de verdad.
- **Falla en cerrado**: la imagen de producción no arranca si la configuración es inválida.
- **Cada guarda se ha visto saltar**, no solo el camino feliz.
- **Ningún aviso se pierde si el destino cae.** El lead y su aviso se guardan en la misma
  transacción (outbox); la entrega se reintenta con backoff exponencial y jitter, un circuit
  breaker deja de insistir contra un destino caído, y lo que agota su plazo se aparta para
  reprocesarlo a mano. En una caída simulada de 30 minutos: **15 o 16 llamadas** al destino, frente
  a 1.800 reintentando cada segundo.
- **El panel de Resiliencia lo demuestra en vivo**: se tumba el CRM simulado, se inyectan leads, la
  cola crece y se drena sola al restablecerlo, sin duplicados.

## Arquitectura

```
                      red del compose
  ┌──────────────────────────────────────────────────────┐
  │                                                      │
  │   app  (Laravel + React, FrankenPHP)   :8010         │
  │    │                                                 │
  │    ├──► db     (PostgreSQL 17)          :5433        │
  │    └──► n8n    (automatización)         :5678        │
  │                                                      │
  │   queue (misma imagen que app, queue:listen)         │
  └──────────────────────────────────────────────────────┘
          todos los puertos publicados solo en 127.0.0.1
```

## Stack

| Capa           | Tecnología                                    |
| -------------- | --------------------------------------------- |
| Backend        | Laravel 13, PHP 8.4                           |
| Frontend       | React 19 + Inertia 3, TypeScript, Tailwind 4  |
| Base de datos  | PostgreSQL 17                                 |
| Automatización | n8n 2.38 (self-hosted)                        |
| Servidor       | FrankenPHP 1.12                               |
| Pruebas        | Pest 5, Playwright 1.63                       |
| CI             | GitHub Actions: gitleaks, calidad, end-to-end |

## Cómo levantarlo

Requisitos: Docker con Compose, PHP 8.4, Composer y Node 22.

```bash
cp .env.example .env
# Generar la clave de n8n y pegarla en N8N_ENCRYPTION_KEY
openssl rand -hex 32

composer install && npm ci
php artisan key:generate
npm run build

docker compose up -d --build --wait
docker compose exec -T app php artisan migrate --force
```

App en `http://127.0.0.1:8010`, editor de n8n en `http://localhost:5678`.

Si tu usuario no tiene UID 1000, añade `UID` y `GID` al `.env` para que el contenedor pueda
escribir en el código montado.

## Pruebas

```bash
composer test        # Pint, PHPStan (nivel 7) y Pest
npm run types:check  # regenera los tipos de rutas y verifica TypeScript
npm run check        # lint y formato del frontend
npm run test:e2e     # Playwright contra los contenedores
```

| Suite                   | Pruebas                                          |
| ----------------------- | ------------------------------------------------ |
| Pest                    | 52 pruebas, 183 aserciones                       |
| Playwright `ui`         | 2                                                |
| Playwright `resilience` | 1 (apaga n8n y verifica que el panel lo detecta) |

Las pruebas de resiliencia **se niegan a correr** contra una URL que no sea local, con algún
servicio detenido, o con assets compilados más viejos que el código de React.

## Decisiones y alternativas descartadas

| Decisión                                          | Alternativas descartadas                           | Por qué                                                                                                                                                                |
| ------------------------------------------------- | -------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Laravel dentro de Docker desde el principio       | n8n con `network_mode: host`; Laravel en `0.0.0.0` | n8n tiene que llamar a la app. La red del host solo funciona en Linux; `0.0.0.0` expone la app a toda la red local                                                     |
| FrankenPHP                                        | nginx + php-fpm                                    | Un solo proceso por contenedor: lo más simple para desplegar un contenedor por servicio                                                                                |
| `/health` consulta solo la base, no n8n           | Incluir n8n                                        | Si n8n cae, la app debe seguir aceptando leads. Un healthcheck con n8n haría que la plataforma reiniciara la app por un servicio del que no depende para recibir datos |
| Cola en base de datos                             | Redis                                              | Las tablas `jobs` y `failed_jobs` son visibles desde el panel: la cola y el DLQ se pueden enseñar                                                                      |
| Validar la configuración en el `CMD` de la imagen | Validar en un service provider                     | FrankenPHP arranca Laravel por petición: una excepción ahí deja el servidor aceptando tráfico y respondiendo 500                                                       |
| PostgreSQL                                        | MySQL                                              | Compatible con un despliegue en Supabase sin migrar                                                                                                                    |
| n8n self-hosted                                   | n8n Cloud                                          | Gratis, mismo editor, y las llamadas salientes (Telegram, Gmail) funcionan sin desplegar                                                                               |

## Documentación

| Archivo                                  | Contenido                                                                |
| ---------------------------------------- | ------------------------------------------------------------------------ |
| [`docs/spec.md`](docs/spec.md)           | Problema, alcance, arquitectura, resiliencia, IA y orden de construcción |
| [`docs/BOOTSTRAP.md`](docs/BOOTSTRAP.md) | Bitácora del arranque: cada fase, lo que se encontró y cómo se verificó  |
