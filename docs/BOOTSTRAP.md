# Bitácora de arranque

Registro de cada fase del arranque siguiendo `engineering-playbook/method/project-bootstrap.md`.
Anota lo que se hizo, lo que se encontró al leer la salida y lo que se decidió.
Lo validado aquí se extrae después a `engineering-playbook/stacks/laravel-react.md`.

**Entorno:** Linux, PHP 8.4.1 (Herd Lite), Composer 2.8.3, Node 22.23, npm 10.9,
Docker 28.3 con Compose 2.29, Laravel Installer 5.14.0.

---

## Fase 0 — Repositorio e instalación

**Fecha:** 13-sep-2026

### Comando

```bash
laravel new reformas-leads-mvp --react --pest --database=pgsql --npm --git --branch=main --no-interaction
```

Con parámetros en lugar del menú interactivo: las respuestas del menú no quedan escritas
en ningún sitio, y así el arranque se puede repetir. Tardó 2 min 17 s.

### Precaución antes de instalar

La carpeta ya existía con `docs/spec.md`. La opción `--force` del instalador **borra la
carpeta completa** antes de instalar. Se copió la spec aparte, se verificó con `sha256sum`,
se borró la carpeta, se instaló y se devolvió la spec comprobando el mismo hash.

### Lo que se encontró al leer la salida

| Esperado | Obtenido | Consecuencia |
|---|---|---|
| Laravel 12 | **Laravel 13.31** | El instalador toma la última estable. No se fija versión |
| PostgreSQL (`--database=pgsql`) | **SQLite** en `.env` y `.env.example` | El `post-create-project-cmd` del starter kit crea `database.sqlite` y migra ahí, ignorando la opción |
| Solo npm | `package-lock.json` **y** `pnpm-workspace.yaml` | El archivo de pnpm viene del starter kit, no de la opción elegida |
| Árbol limpio | `composer.json` modificado sin commitear | Solo formato: arreglo vacío reescrito y salto de línea final quitado |

### Lo que trae el starter kit de React

- **Backend:** Inertia 3, Fortify (autenticación, 2FA, passkeys), Wayfinder (rutas tipadas
  para TypeScript), Larastan, Pint, Pest 5, Sail.
- **Frontend:** React 19, TypeScript 5.7, Tailwind 4, Vite 8 a través de `vite-plus` (`vp`).
- **Git:** dos commits del instalador ("Set up a fresh Laravel app", "Install Pest").
- **CI:** `.github/workflows/tests.yml` y `dependabot.yml`.
- **`.npmrc` con `ignore-scripts=true`:** bloquea los scripts de instalación de
  dependencias. La misma protección que pnpm 12 aplica por defecto.
- **`composer run dev`** ahora llama a `php artisan dev`, que orquesta servidor, cola,
  registros y Vite.
- **`composer run test`** ya encadena Pint, Larastan y las pruebas.
- **Ruta de salud `/up`** declarada en `bootstrap/app.php`. **No consulta la base de
  datos**: solo confirma que el proceso responde. Se sustituye en la Fase 4.

---

## Fase 1 — Estructura

**No aplica.** El starter kit de React genera **una sola aplicación** con el frontend
dentro (Inertia): no hay apps separadas que justifiquen un monorepo. n8n y PostgreSQL no
son aplicaciones del repositorio sino servicios, y van en `docker-compose.yml` (Fase 3).

---

## Fase 2 — Verificar que arranca, y solo después limpiar

**Fecha:** 13-sep-2026

### Verificación tal como lo dejó el generador

| Comprobación | Resultado |
|---|---|
| `php artisan serve` en 8010 | `/`, `/login`, `/register` y `/up` → 200 |
| Ruta protegida | `/dashboard` → 302 a `/login` |
| Assets compilados | `app-*.js` → 200 |
| `php artisan test` | **39 pruebas, 136 aserciones, en verde** |

Las pruebas usan SQLite en memoria (`phpunit.xml`), no la base del `.env`.

### Limpieza, ya con la línea base en verde

**`pnpm-workspace.yaml` eliminado.** El proyecto usa npm (`package-lock.json`); dos
gestores de paquetes a la vez permiten resolver versiones distintas según quién instale.
Antes de borrarlo se comprobó que no hacía falta: apartado el archivo, `npm run build`
(2313 módulos) y `npm run types:check` pasaron.

### Puertos

Los puertos por defecto chocaban con otro proyecto corriendo en la misma máquina
(8000 y 5173), y los de `nexora-erp` ocupan 3000, 3001 y 5432.

| Servicio | Puerto | Dónde se fija |
|---|---|---|
| App (`artisan serve`) | 8010 | `SERVER_PORT` y `APP_URL` en `.env` / `.env.example` |
| Vite | 5174 | `server.port` en `vite.config.ts`, con `strictPort: true` |

`strictPort` hace que Vite **falle** si el puerto está ocupado, en vez de saltar en
silencio al siguiente y dejar la app pidiendo assets a un puerto donde no hay nadie.

**Verificado con `composer run dev` completo**, no leyendo el código: el log muestra
`Server running on [http://127.0.0.1:8010]`, Vite en `localhost:5174`, y `public/hot`
apunta a `http://127.0.0.1:5174`, que es lo que la vista usa para pedir `@vite/client`.

### `composer run dev` por dentro

`php artisan dev` levanta cuatro procesos bajo `@laravel/multiplex`:

| Proceso | Comando |
|---|---|
| server | `php artisan serve` |
| queue | `php artisan queue:listen --tries=1 --timeout=0` |
| logs | `php artisan pail --timeout=0` |
| vite | `npm run dev` → `vp dev` |

La cola ya viene levantada en desarrollo, así que los jobs del outbox corren sin un paso
adicional.

### Tropiezos

- **Apagar `composer run dev` lanzado en segundo plano.** `setsid` se desprende en un
  proceso aparte, así que matar su PID no alcanza a los hijos: servidor, cola, logs y Vite
  siguieron corriendo. Se detuvieron filtrando procesos por directorio de trabajo, para no
  tocar los de otros proyectos. En uso normal no ocurre: `Ctrl+C` en la terminal detiene
  el grupo completo.
- **`public/hot` queda huérfano** si Vite no se cierra limpio. Mientras exista, la app
  pide los assets a un servidor de Vite que no está corriendo y la página sale sin
  estilos. Se borra a mano; está en `.gitignore`.

---

## Fase 3 — Servicios en contenedor

**Fecha:** 13-sep-2026

Solo los servicios de apoyo, no la aplicación: **PostgreSQL** y **n8n**. La app de
Laravel sigue en local con `composer run dev`.

### Versiones fijas

| Servicio | Imagen | Cómo se eligió |
|---|---|---|
| PostgreSQL | `postgres:17-alpine` | Misma que `nexora-erp`; compatible con Supabase |
| n8n | `n8nio/n8n:2.38.7` | Es la versión a la que apuntan `stable` y `latest` (mismo digest). La 2.39.x es más nueva pero aún no está marcada como estable |

**Buscar la versión estable de n8n en Docker Hub no es directo:** ordenado por
actualización, las primeras páginas son todas compilaciones nocturnas. Se consultó el
digest de `stable` y se buscó qué tag numerado tenía el mismo digest:

```bash
curl -s https://hub.docker.com/v2/repositories/n8nio/n8n/tags/stable
curl -s "https://hub.docker.com/v2/repositories/n8nio/n8n/tags?name=2.&page_size=100"
```

### Una sola fuente para las credenciales

Compose lee el mismo `.env` que Laravel. El contenedor de Postgres toma `DB_USERNAME`,
`DB_PASSWORD` y `DB_DATABASE` en lugar de tener sus propias `POSTGRES_*`: si fueran dos
juegos de variables, cambiar la contraseña en uno y no en el otro rompe la conexión.

### Guardas verificadas

| Guarda | Cómo se comprobó |
|---|---|
| `N8N_ENCRYPTION_KEY` obligatoria (`:?`) | `docker compose --env-file .env.example config` → **se niega a arrancar** con `falta N8N_ENCRYPTION_KEY en .env` |
| Puertos solo en `127.0.0.1` | `docker compose config` muestra `host_ip: 127.0.0.1` en ambos |
| Healthchecks | `docker compose up -d --wait` no termina hasta que los dos están `healthy` (1 min 27 s con la descarga de n8n) |

`N8N_ENCRYPTION_KEY` cifra las credenciales que se guardan dentro de n8n (tokens de
Telegram, OAuth de Gmail). **Si cambia después de crearlas, n8n ya no puede leerlas.** Por
eso no tiene valor por defecto y se genera una sola vez con `openssl rand -hex 32`.

### Laravel pasa de SQLite a PostgreSQL

`DB_CONNECTION=pgsql` en `.env`, puerto 5433. `php artisan migrate` aplicó las cinco
migraciones del starter kit; `php artisan db:show` confirma PostgreSQL 17.11, base
`reformas`, 10 tablas.

### n8n

`/healthz` → `{"status":"ok"}`, editor en `http://localhost:5678` → 200,
`n8n --version` → 2.38.7.

### Hallazgo: n8n no puede llamar a Laravel tal como está

n8n corre en un contenedor y Laravel en el host. Desde dentro de un contenedor,
`localhost` es el propio contenedor. Se hicieron experimentos con servidores PHP mínimos
en vez de suponer:

| Experimento | Resultado |
|---|---|
| Contenedor **sin** `extra_hosts` → `host.docker.internal` | `ENOTFOUND`: el nombre no existe en Linux |
| Con `extra_hosts: host-gateway` → servidor en `127.0.0.1` | `ECONNREFUSED` |
| Con `host-gateway` → servidor en `172.21.0.1` (puente del compose) | `ECONNREFUSED` |
| Con `host-gateway` → servidor en **`172.17.0.1`** (puente `docker0`) | **OK**, pero el navegador ya no llega por `127.0.0.1` |
| Contenedor con `--network host` → servidor en `127.0.0.1` | **OK** |
| `SERVER_HOST=127.0.0.2 php artisan serve` | Escucha en `127.0.0.2`: **`artisan serve` respeta `SERVER_HOST`** |

**La causa:** en Docker Engine nativo sobre Linux, `host-gateway` resuelve a la IP del
puente **`docker0` (172.17.0.1)**, no a la del puente de la red del compose. Un servidor
que escucha solo en `127.0.0.1` no es alcanzable desde ningún puente.

**Error de diagnóstico que costó un experimento:** la salida de Compose decía
`v2.29.7-desktop.1` y se sospechó de Docker Desktop (contenedores dentro de una VM).
`docker context show` → `default` y `docker info` → Ubuntu nativo lo descartaron: el
sufijo es del binario, no del motor. **Comprobar el contexto antes de razonar sobre la
red.**

**Decisión:** Laravel se dockeriza ya (Fase 7 adelantada) y los tres servicios comparten
la red del compose, comunicándose por nombre (`app`, `db`, `n8n`). La regla del playbook
es *no dockerizar lo que todavía no corre*; Laravel ya corre y está verificado, así que
esperar no aporta nada. Se descartaron: n8n con `network_mode: host` (solo Linux) y
Laravel en `0.0.0.0` (expone la app a la red local).

---

## Fase 4 — Endpoint de salud

**Fecha:** 13-sep-2026

### Por qué no sirve el `/up` de Laravel

El starter kit declara `health: '/up'` en `bootstrap/app.php`. Responde 200 si el proceso
está vivo, **sin consultar la base**. Se eliminó y se sustituyó por `/health`.

### Implementación

- `app/Http/Controllers/HealthController.php`: `select 1` a la base. Si falla, **503** con
  `{"status":"error","database":"down"}`. No se devuelve el mensaje de la excepción: puede
  revelar el host y el usuario de la base.
- Registrada en `bootstrap/app.php` dentro de `then:`, **fuera del grupo `web`**.

**La trampa específica de Laravel:** el starter kit guarda las sesiones en la base
(`SESSION_DRIVER=database`). Una ruta en `routes/web.php` pasa por el middleware de
sesión, que consulta la base **antes** de llegar al controlador. Con la base caída,
revienta ahí y la respuesta es **500, no 503**: el chequeo nunca llega a ejecutarse.

### Pruebas (`tests/Feature/HealthTest.php`)

| Prueba | Qué garantiza |
|---|---|
| Base responde → 200 | El camino feliz |
| `DB::select` lanza excepción → 503 | **La guarda salta** |
| No se emite cookie de sesión | Que nadie la mueva al grupo `web` sin darse cuenta |

### Verificación contra el PostgreSQL real

| Paso | Respuesta |
|---|---|
| Base arriba | 200 `{"status":"ok","database":"up"}` |
| `docker compose stop db` | **503** `{"status":"error","database":"down"}` |
| `docker compose start db`, **sin reiniciar la app** | 200: se recupera sola |
| Cabeceras `Set-Cookie` en `/health` | 0 |

### Tropiezo: `composer run test` se corta en PHPStan

El PHP de Herd Lite trae `memory_limit=128M`. El análisis en paralelo de PHPStan lo
supera y el proceso muere con *"reached configured PHP memory limit: 128M"*. Como
`composer run test` encadena Pint → PHPStan → Pest, **las pruebas ni siquiera llegan a
correr**, y la salida puede leerse como si fallara el código.

**Arreglo en el repositorio, no en la máquina:** `phpstan analyse --memory-limit=1G` en
el script `types:check` de `composer.json`. Subir el `php.ini` arreglaría solo esta
máquina; el flag funciona en cualquier clon y en el CI.

El aviso `Unable to load dynamic library ... phpstan_turbo-8.4.so (Dynamic loading not
supported)` es independiente e inofensivo: el PHP de Herd Lite es estático y no carga
extensiones; PHPStan funciona igual sin su acelerador.

---

## Fase 7 — Dockerizar la aplicación (adelantada)

**Fecha:** 13-sep-2026

Se adelantó porque la integración con n8n necesita que los servicios compartan red, y
Laravel ya corría y estaba verificado (la razón de esperar ya se había cumplido).

### Cuatro servicios, una red

| Servicio | Imagen | Rol |
|---|---|---|
| `app` | `reformas-leads-app:dev` | Laravel servido por FrankenPHP en 8010 |
| `queue` | la misma imagen | `php artisan queue:listen` |
| `db` | `postgres:17-alpine` | Base de datos |
| `n8n` | `n8nio/n8n:2.38.7` | Automatización |

Cada proceso en su contenedor; lo que los agrupa es la **red del compose**, que trae DNS
propio: `app`, `db` y `n8n` resuelven por nombre. **n8n → `http://app:8010/health` → 200**.

### Imagen base: FrankenPHP

`dunglas/frankenphp:1.12.7-php8.4-alpine`. Sirve PHP en un solo proceso (Caddy integrado),
sin montar nginx + php-fpm. Para un despliegue de un contenedor por servicio es lo más
simple. Lo que hubo que saber de ella, inspeccionándola antes de escribir el Dockerfile:

| Hallazgo | Consecuencia |
|---|---|
| Corre como **root** | Usuario propio `app` |
| Trae `install-php-extensions` | `pdo_pgsql` y `pcntl` en una línea |
| **No trae** `pdo_pgsql`, `pcntl`, Composer ni Node | Se instalan o se copian por etapa |
| El Caddyfile lee `SERVER_NAME` | `SERVER_NAME=:8010` → HTTP en ese puerto, sin TLS |
| Trae `HEALTHCHECK curl localhost:2019/metrics` | **Ver tropiezo abajo** |
| Alpine 3.24 → `apk add nodejs` da **Node 24.18** | Cumple Vite 8 (≥ 22.12); en el host se usa Node 22 |

### Etapas del Dockerfile

| Etapa | Qué hace |
|---|---|
| `base` | Extensiones, Composer, usuario `app` con el UID del host |
| `dev` | Solo el entorno; el código llega por volumen |
| `vendor` | `composer install --no-dev` |
| `assets` | `npm ci` + `npm run build`. **Necesita PHP y Node a la vez**: el plugin de Wayfinder ejecuta `php artisan` durante el build de Vite |
| `prod` | Código + `vendor` + `public/build`; **se borra Composer**; `USER app` |

`.dockerignore` escrito **antes** del primer build: fuera `vendor`, `node_modules`, `.env`,
`tests`, `docs`, `.git` y el estado de `storage`.

### Permisos con el código montado

El usuario del contenedor se crea con `UID`/`GID` del host (1000). Un archivo creado
dentro del contenedor en `storage/logs` aparece en el host como `medine01:medine01`. Sin
esto, Laravel escribe logs y caché como otro usuario y no se pueden editar ni borrar.

### Variables dentro de la red

`DB_HOST=db` y `DB_PORT=5432` se inyectan desde el compose. Las variables de entorno
reales **tienen prioridad** sobre el `.env` que Laravel lee, así que el mismo `.env` sirve
para los contenedores (`db:5432`) y para herramientas que corran en el host
(`127.0.0.1:5433`).

### Verificación

**Desarrollo** (`docker compose up -d --wait`):

| Comprobación | Resultado |
|---|---|
| `app` | healthy; `/health` 200, `/login` 200 |
| n8n → `http://app:8010/health` | **200** |
| Proceso | usuario `app`, uid 1000, `pdo_pgsql` y `pcntl` cargados |
| `php artisan db:show` dentro | PostgreSQL 17.11 en `db:5432` |
| Dueño de un archivo creado dentro, visto desde el host | `medine01:medine01` |
| `queue` | `Processing jobs from the [default] queue` |

**Producción** (`docker build --target prod`), **arrancada**, no solo construida:

| Comprobación | Resultado |
|---|---|
| Tamaño | 238 MB (`vendor` 56 MB, `public/build` 1 MB) |
| Usuario / Composer / Node / `tests` / `.env` | `app` / eliminado / ausente / ausente / ausente |
| `/`, `/login`, `/register` | 200; `/dashboard` 302; assets 200 |
| `php artisan about` | `production`, Debug **OFF** |
| Errores de Laravel o PHP en los logs | ninguno |
| Healthcheck de Docker | **healthy** en ~7 s |
| El comando del healthcheck contra una ruta 404 | sale con **1**: la guarda salta |

### Tropiezos

**El healthcheck heredado de FrankenPHP.** La imagen base comprueba el panel de
administración de Caddy (`:2019/metrics`). Dos problemas:

1. `queue` usa la misma imagen pero no levanta servidor web → quedaba **unhealthy**
   mientras procesaba jobs sin problema.
2. En producción diría **healthy con la base caída**: justo lo que la Fase 4 evita.

Arreglo: `HEALTHCHECK NONE` en `base` y uno propio contra `/health` en `prod`. En
desarrollo, `app` usa el del compose.

**Un falso negativo de la comprobación, no de la imagen.** La primera prueba de
producción dio `000` en todas las rutas. `curl --retry-connrefused` solo reintenta ante
*conexión rechazada*; en el instante de arrancar, el reenvío de puertos de Docker
**corta** la conexión en vez de rechazarla, y curl se rindió al primer intento. Relanzada
esperando al contenedor, respondió 200. Y como se había borrado el contenedor al terminar,
**se perdieron los logs**: al verificar algo que puede fallar, conservar el contenedor
hasta leerlos.

### Flujo de desarrollo después de dockerizar

```bash
docker compose up -d --wait   # app, queue, db y n8n
npm run dev                   # Vite en el host, puerto 5174
```

Vite sigue en el host: escribe `public/hot` en el código montado, la app del contenedor
lo lee, y el navegador pide los assets a `127.0.0.1:5174`. **`composer run dev` ya no se
usa**: intentaría levantar su propio servidor en 8010, que ahora ocupa el contenedor.
