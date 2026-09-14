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

| Esperado                        | Obtenido                                        | Consecuencia                                                                                         |
| ------------------------------- | ----------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Laravel 12                      | **Laravel 13.31**                               | El instalador toma la última estable. No se fija versión                                             |
| PostgreSQL (`--database=pgsql`) | **SQLite** en `.env` y `.env.example`           | El `post-create-project-cmd` del starter kit crea `database.sqlite` y migra ahí, ignorando la opción |
| Solo npm                        | `package-lock.json` **y** `pnpm-workspace.yaml` | El archivo de pnpm viene del starter kit, no de la opción elegida                                    |
| Árbol limpio                    | `composer.json` modificado sin commitear        | Solo formato: arreglo vacío reescrito y salto de línea final quitado                                 |

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

| Comprobación                | Resultado                                |
| --------------------------- | ---------------------------------------- |
| `php artisan serve` en 8010 | `/`, `/login`, `/register` y `/up` → 200 |
| Ruta protegida              | `/dashboard` → 302 a `/login`            |
| Assets compilados           | `app-*.js` → 200                         |
| `php artisan test`          | **39 pruebas, 136 aserciones, en verde** |

Las pruebas usan SQLite en memoria (`phpunit.xml`), no la base del `.env`.

### Limpieza, ya con la línea base en verde

**`pnpm-workspace.yaml` eliminado.** El proyecto usa npm (`package-lock.json`); dos
gestores de paquetes a la vez permiten resolver versiones distintas según quién instale.
Antes de borrarlo se comprobó que no hacía falta: apartado el archivo, `npm run build`
(2313 módulos) y `npm run types:check` pasaron.

### Puertos

Los puertos por defecto chocaban con otro proyecto corriendo en la misma máquina
(8000 y 5173), y los de `nexora-erp` ocupan 3000, 3001 y 5432.

| Servicio              | Puerto | Dónde se fija                                             |
| --------------------- | ------ | --------------------------------------------------------- |
| App (`artisan serve`) | 8010   | `SERVER_PORT` y `APP_URL` en `.env` / `.env.example`      |
| Vite                  | 5174   | `server.port` en `vite.config.ts`, con `strictPort: true` |

`strictPort` hace que Vite **falle** si el puerto está ocupado, en vez de saltar en
silencio al siguiente y dejar la app pidiendo assets a un puerto donde no hay nadie.

**Verificado con `composer run dev` completo**, no leyendo el código: el log muestra
`Server running on [http://127.0.0.1:8010]`, Vite en `localhost:5174`, y `public/hot`
apunta a `http://127.0.0.1:5174`, que es lo que la vista usa para pedir `@vite/client`.

### `composer run dev` por dentro

`php artisan dev` levanta cuatro procesos bajo `@laravel/multiplex`:

| Proceso | Comando                                          |
| ------- | ------------------------------------------------ |
| server  | `php artisan serve`                              |
| queue   | `php artisan queue:listen --tries=1 --timeout=0` |
| logs    | `php artisan pail --timeout=0`                   |
| vite    | `npm run dev` → `vp dev`                         |

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

| Servicio   | Imagen               | Cómo se eligió                                                                                                                  |
| ---------- | -------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| PostgreSQL | `postgres:17-alpine` | Misma que `nexora-erp`; compatible con Supabase                                                                                 |
| n8n        | `n8nio/n8n:2.38.7`   | Es la versión a la que apuntan `stable` y `latest` (mismo digest). La 2.39.x es más nueva pero aún no está marcada como estable |

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

| Guarda                                  | Cómo se comprobó                                                                                                 |
| --------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `N8N_ENCRYPTION_KEY` obligatoria (`:?`) | `docker compose --env-file .env.example config` → **se niega a arrancar** con `falta N8N_ENCRYPTION_KEY en .env` |
| Puertos solo en `127.0.0.1`             | `docker compose config` muestra `host_ip: 127.0.0.1` en ambos                                                    |
| Healthchecks                            | `docker compose up -d --wait` no termina hasta que los dos están `healthy` (1 min 27 s con la descarga de n8n)   |

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

| Experimento                                                          | Resultado                                                         |
| -------------------------------------------------------------------- | ----------------------------------------------------------------- |
| Contenedor **sin** `extra_hosts` → `host.docker.internal`            | `ENOTFOUND`: el nombre no existe en Linux                         |
| Con `extra_hosts: host-gateway` → servidor en `127.0.0.1`            | `ECONNREFUSED`                                                    |
| Con `host-gateway` → servidor en `172.21.0.1` (puente del compose)   | `ECONNREFUSED`                                                    |
| Con `host-gateway` → servidor en **`172.17.0.1`** (puente `docker0`) | **OK**, pero el navegador ya no llega por `127.0.0.1`             |
| Contenedor con `--network host` → servidor en `127.0.0.1`            | **OK**                                                            |
| `SERVER_HOST=127.0.0.2 php artisan serve`                            | Escucha en `127.0.0.2`: **`artisan serve` respeta `SERVER_HOST`** |

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
es _no dockerizar lo que todavía no corre_; Laravel ya corre y está verificado, así que
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

| Prueba                             | Qué garantiza                                      |
| ---------------------------------- | -------------------------------------------------- |
| Base responde → 200                | El camino feliz                                    |
| `DB::select` lanza excepción → 503 | **La guarda salta**                                |
| No se emite cookie de sesión       | Que nadie la mueva al grupo `web` sin darse cuenta |

### Verificación contra el PostgreSQL real

| Paso                                                | Respuesta                                      |
| --------------------------------------------------- | ---------------------------------------------- |
| Base arriba                                         | 200 `{"status":"ok","database":"up"}`          |
| `docker compose stop db`                            | **503** `{"status":"error","database":"down"}` |
| `docker compose start db`, **sin reiniciar la app** | 200: se recupera sola                          |
| Cabeceras `Set-Cookie` en `/health`                 | 0                                              |

### Tropiezo: `composer run test` se corta en PHPStan

El PHP de Herd Lite trae `memory_limit=128M`. El análisis en paralelo de PHPStan lo
supera y el proceso muere con _"reached configured PHP memory limit: 128M"_. Como
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

| Servicio | Imagen                   | Rol                                    |
| -------- | ------------------------ | -------------------------------------- |
| `app`    | `reformas-leads-app:dev` | Laravel servido por FrankenPHP en 8010 |
| `queue`  | la misma imagen          | `php artisan queue:listen`             |
| `db`     | `postgres:17-alpine`     | Base de datos                          |
| `n8n`    | `n8nio/n8n:2.38.7`       | Automatización                         |

Cada proceso en su contenedor; lo que los agrupa es la **red del compose**, que trae DNS
propio: `app`, `db` y `n8n` resuelven por nombre. **n8n → `http://app:8010/health` → 200**.

### Imagen base: FrankenPHP

`dunglas/frankenphp:1.12.7-php8.4-alpine`. Sirve PHP en un solo proceso (Caddy integrado),
sin montar nginx + php-fpm. Para un despliegue de un contenedor por servicio es lo más
simple. Lo que hubo que saber de ella, inspeccionándola antes de escribir el Dockerfile:

| Hallazgo                                           | Consecuencia                                       |
| -------------------------------------------------- | -------------------------------------------------- |
| Corre como **root**                                | Usuario propio `app`                               |
| Trae `install-php-extensions`                      | `pdo_pgsql` y `pcntl` en una línea                 |
| **No trae** `pdo_pgsql`, `pcntl`, Composer ni Node | Se instalan o se copian por etapa                  |
| El Caddyfile lee `SERVER_NAME`                     | `SERVER_NAME=:8010` → HTTP en ese puerto, sin TLS  |
| Trae `HEALTHCHECK curl localhost:2019/metrics`     | **Ver tropiezo abajo**                             |
| Alpine 3.24 → `apk add nodejs` da **Node 24.18**   | Cumple Vite 8 (≥ 22.12); en el host se usa Node 22 |

### Etapas del Dockerfile

| Etapa    | Qué hace                                                                                                                            |
| -------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| `base`   | Extensiones, Composer, usuario `app` con el UID del host                                                                            |
| `dev`    | Solo el entorno; el código llega por volumen                                                                                        |
| `vendor` | `composer install --no-dev`                                                                                                         |
| `assets` | `npm ci` + `npm run build`. **Necesita PHP y Node a la vez**: el plugin de Wayfinder ejecuta `php artisan` durante el build de Vite |
| `prod`   | Código + `vendor` + `public/build`; **se borra Composer**; `USER app`                                                               |

`.dockerignore` escrito **antes** del primer build: fuera `vendor`, `node_modules`, `.env`,
`tests`, `docs`, `.git` y el estado de `storage`.

### Permisos con el código montado

El usuario del contenedor se crea con `UID`/`GID` del host (1000). Un archivo creado
dentro del contenedor en `storage/logs` aparece en el host a nombre del usuario del host. Sin
esto, Laravel escribe logs y caché como otro usuario y no se pueden editar ni borrar.

### Variables dentro de la red

`DB_HOST=db` y `DB_PORT=5432` se inyectan desde el compose. Las variables de entorno
reales **tienen prioridad** sobre el `.env` que Laravel lee, así que el mismo `.env` sirve
para los contenedores (`db:5432`) y para herramientas que corran en el host
(`127.0.0.1:5433`).

### Verificación

**Desarrollo** (`docker compose up -d --wait`):

| Comprobación                                           | Resultado                                               |
| ------------------------------------------------------ | ------------------------------------------------------- |
| `app`                                                  | healthy; `/health` 200, `/login` 200                    |
| n8n → `http://app:8010/health`                         | **200**                                                 |
| Proceso                                                | usuario `app`, uid 1000, `pdo_pgsql` y `pcntl` cargados |
| `php artisan db:show` dentro                           | PostgreSQL 17.11 en `db:5432`                           |
| Dueño de un archivo creado dentro, visto desde el host | el usuario del host                                     |
| `queue`                                                | `Processing jobs from the [default] queue`              |

**Producción** (`docker build --target prod`), **arrancada**, no solo construida:

| Comprobación                                   | Resultado                                       |
| ---------------------------------------------- | ----------------------------------------------- |
| Tamaño                                         | 238 MB (`vendor` 56 MB, `public/build` 1 MB)    |
| Usuario / Composer / Node / `tests` / `.env`   | `app` / eliminado / ausente / ausente / ausente |
| `/`, `/login`, `/register`                     | 200; `/dashboard` 302; assets 200               |
| `php artisan about`                            | `production`, Debug **OFF**                     |
| Errores de Laravel o PHP en los logs           | ninguno                                         |
| Healthcheck de Docker                          | **healthy** en ~7 s                             |
| El comando del healthcheck contra una ruta 404 | sale con **1**: la guarda salta                 |

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
_conexión rechazada_; en el instante de arrancar, el reenvío de puertos de Docker
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

---

## Fase 5 — Primer flujo completo

**Fecha:** 13-sep-2026

El panel muestra el estado de la base de datos y de n8n, consultado desde Laravel. Es el
primer recorrido de punta a punta: navegador → Laravel → PostgreSQL y n8n.

### Diseño

- `App\Support\SystemStatus`: `databaseIsUp()` y `n8nIsUp()`. `HealthController` reutiliza
  el chequeo de la base en lugar de duplicarlo.
- `DashboardController` sustituye al `Route::inertia` estático y pasa `systemStatus` a la
  página.
- `N8N_BASE_URL`: `http://127.0.0.1:5678` en el `.env` (host) y `http://n8n:5678` inyectada
  por el compose (contenedor).
- Una tarjeta por servicio con `data-test="status-<servicio>"` (la convención del starter kit, no `data-testid`) y `data-state="up|down"`,
  sembrados **al escribir el componente**.

**`/health` sigue mirando solo la base, a propósito.** Si n8n cae, la app debe seguir
aceptando leads, que es para lo que existe el outbox. Un healthcheck que incluyera n8n
haría que Docker o la plataforma de despliegue dieran la app por caída —y la reiniciaran—
por un fallo de un servicio del que no depende para recibir datos.

**Timeout de 2 s hacia n8n.** Un n8n que no contesta no puede congelar el panel: es
justo cuando más se necesita verlo.

### Pruebas

`tests/Feature/DashboardStatusTest.php`: todo arriba; n8n responde 503; n8n no contesta
(`ConnectionException`). En los tres casos el panel responde 200.

**`Http::preventStrayRequests()` en `tests/TestCase.php`.** Sin esto, la prueba del
dashboard del starter kit habría llamado **de verdad** a n8n: en local encontraría el
contenedor corriendo y pasaría; en el CI esperaría el timeout. Una prueba que depende de
un servicio externo pasa o falla según la máquina.

Resultado: **45 pruebas, 170 aserciones**; PHPStan 0 errores; `vp check` sin avisos en 64
archivos; tipos en verde.

### Verificación contra los contenedores

Inicio de sesión real con cookies y token CSRF, leyendo las props que recibe la página:

| Paso                      | `systemStatus`                                           |
| ------------------------- | -------------------------------------------------------- |
| Todo arriba               | `{"database":true,"n8n":true}`                           |
| `docker compose stop n8n` | `{"database":true,"n8n":false}`, página en 488 ms        |
| n8n de vuelta y healthy   | `false` en la primera carga; **`true` segundos después** |

**Inertia 3 cambió dónde viajan las props:** ya no en el atributo `data-page` del `div`
raíz, sino en `<script data-page="app" type="application/json">`. Un script que las busque
con el formato de Inertia 2 no encuentra nada.

### Tropiezo: un fallo transitorio que parecía persistente

Tras reiniciar n8n, la primera carga seguía diciendo `false`. Hipótesis: caché de DNS en
los procesos persistentes de FrankenPHP con n8n en otra IP. **Descartada midiendo**: la IP
no cambió (`172.21.0.2`), `getent hosts n8n` la resolvía bien, y PHP puro, Laravel en un
proceso nuevo y el panel devolvieron `true` en los intentos siguientes. Era una ventana de
segundos justo después del arranque.

**Consecuencia para las pruebas:** una prueba de resiliencia que reinicia n8n no puede
comprobar una sola vez; tiene que reintentar hasta ver el servicio arriba. Si no, es una
prueba intermitente.

---

## Fase 6 — Suite de pruebas

**Fecha:** 14-sep-2026

### Separada por propósito

| Suite               | Herramienta                         | Qué prueba                                | ¿Navegador? |
| ------------------- | ----------------------------------- | ----------------------------------------- | ----------- |
| Contratos y dominio | Pest (`tests/`)                     | HTTP, reglas, casos de error              | No          |
| `ui`                | Playwright (`e2e/tests/ui`)         | Lo que ve el usuario                      | Sí          |
| `resilience`        | Playwright (`e2e/tests/resilience`) | Que el sistema detecta sus propios fallos | Sí          |

Playwright **1.63.0** fijado sin `^`. El Chromium que pide (v1243) ya estaba en caché.

### Decisiones

- **`testIdAttribute: 'data-test'`.** El starter kit ya usa `data-test` (`login-button`,
  `logout-button`). Se corrigió el `data-testid` del dashboard para que haya una sola
  convención.
- **Page Object Model desde el primer archivo** (`e2e/pages/`). Ningún `.spec.ts` conoce un
  selector.
- **`resilience` depende de `ui`**: corre al final, porque apaga contenedores y dejaría sin
  servicio a las demás si corrieran a la vez.
- **Restaurar pase lo que pase:** `afterAll` levanta n8n aunque la prueba falle.
- **Reintentar hasta ver el cambio**, no comprobar una vez: tras reiniciar n8n hay una
  ventana de segundos en que todavía no responde (medida en la Fase 5). `waitForService`
  recarga la página dentro de `expect(...).toPass()`.
- **Seeder idempotente:** `global-setup` lo ejecuta al empezar para **garantizar** el usuario
  de prueba sin borrar la base de desarrollo.

### Guardas en `global-setup`, verificadas

| Guarda                              | Cómo se provocó                   | Mensaje                                                                         |
| ----------------------------------- | --------------------------------- | ------------------------------------------------------------------------------- |
| URL que no es local                 | `E2E_BASE_URL=http://example.com` | `Las pruebas apagan servicios; se niegan a correr contra http://example.com`    |
| Servicio detenido                   | `docker compose stop n8n`         | `Servicios detenidos: n8n. Ejecuta: docker compose up -d --wait`                |
| **Assets más viejos que el código** | `touch resources/js/app.tsx`      | `Los assets compilados son más viejos que resources/js. Ejecuta: npm run build` |

La tercera es propia de este stack. Con los contenedores levantados y sin Vite corriendo,
la app sirve `public/build`. Si ese build es anterior al último cambio en React, **las
pruebas verifican una interfaz que ya no existe y pueden pasar en verde**. La guarda
compara la fecha de `public/build/manifest.json` con el archivo más reciente de
`resources/js`, y se salta si existe `public/hot` (Vite en marcha).

### Tipos de las pruebas

El `tsconfig.json` de la raíz solo incluye `resources/js`: **nadie verificaba los tipos de
`e2e/`**. Se añadió `e2e/tsconfig.json` (resolución `bundler`, como el resto) y el script
`types:check` ahora es `tsc --noEmit && tsc -p e2e/tsconfig.json`.

### Resultado

```
Running 3 tests using 1 worker
  ✓ [ui] guests are sent to the login page
  ✓ [ui] a signed-in user sees every service up
  ✓ [resilience] the panel detects n8n going down and coming back (15.8s)
  3 passed (22.2s)
```

n8n queda `healthy` al terminar.

---

## Fase 8 — Integración continua

**Fecha:** 14-sep-2026

`.github/workflows/ci.yml` sustituye al `tests.yml` del starter kit, adaptando la plantilla
de `engineering-playbook/templates/ci.yml`.

### Por qué no servía el workflow del starter kit

| Del starter kit      | Problema                                                                          |
| -------------------- | --------------------------------------------------------------------------------- |
| `php-version: '8.3'` | La imagen usa PHP 8.4: el CI verificaría otra versión                             |
| `composer setup`     | Migra con `.env.example`, que ahora apunta a PostgreSQL; en el runner no hay base |
| Un solo trabajo      | Sin escaneo de secretos ni pruebas end-to-end                                     |

### Tres trabajos en paralelo

```
push / pull_request
 ├── secrets   gitleaks sobre todo el historial
 ├── quality   composer test (Pint + PHPStan + Pest) · types:check · vp check
 └── e2e       docker compose up (el mismo que en local) · migrate · Playwright
```

No hay trabajo de publicación del reporte: **GitHub Pages no está disponible en
repositorios privados con plan gratuito**. El reporte de Playwright se sube como
artefacto (`if: always()`, solo si existe) y los logs de Docker cuando algo falla.

Las acciones del starter kit ya venían **fijadas por SHA** (`checkout`, `setup-php`,
`setup-node`); se conservaron. `cache` y `upload-artifact` van por etiqueta mayor.

### Detalles del runner

- **UID distinto.** El usuario del runner no es 1000. Sin pasar `UID`/`GID` al compose, el
  contenedor escribe como otro usuario y no puede crear logs ni caché en el código montado.
  Se añaden al `.env` antes de `docker compose up --build`.
- **Clave de n8n desechable** con `openssl rand -hex 32`: el runner nace y muere con la
  corrida.
- **`npm run build` antes de levantar**: el contenedor de desarrollo sirve `public/build`
  desde el código montado, y la guarda de `global-setup` exige que sea reciente.
- Instalación de navegadores con **tres reintentos**: `--with-deps` usa apt y falla de forma
  transitoria.

### Código derivado: los tipos de rutas de Wayfinder

`tsc` necesita `resources/js/{actions,routes,wayfinder}`, que genera Wayfinder y están en
`.gitignore`. **En un clon limpio no existen.** Experimento: se apartaron los 58 archivos
generados y `types:check` dio **38 errores de TypeScript**; con
`php artisan wayfinder:generate --with-form` volvió a pasar, y la salida resultó **idéntica**
a la del plugin de Vite.

Arreglo en el punto donde nace la necesidad, no como paso suelto del pipeline:

```json
"types:check": "php artisan wayfinder:generate --with-form && tsc --noEmit && tsc -p e2e/tsconfig.json"
```

**No sirve un script `pretypes:check`.** El `.npmrc` del starter kit tiene
`ignore-scripts=true`, que además de bloquear los scripts de instalación de dependencias
**desactiva los scripts `pre` y `post`** de npm. El enganche no se ejecutaría y no habría
ningún aviso.

### Verificaciones antes del primer push

| Comprobación                                             | Resultado                       |
| -------------------------------------------------------- | ------------------------------- |
| `actionlint` sobre `ci.yml`                              | sin hallazgos                   |
| gitleaks sobre todo el historial, misma imagen que el CI | 9 commits, **sin filtraciones** |

---

## Fase 10 — Configuración por entorno

**Fecha:** 14-sep-2026

### Qué se valida

`App\Support\ProductionConfiguration::problems()`. **Fuera de producción no exige nada**:
los valores por defecto solo existen para desarrollar.

| Regla                                    | Por qué                                                                 |
| ---------------------------------------- | ----------------------------------------------------------------------- |
| `APP_KEY` no vacía                       | Sin ella no hay sesiones ni cifrado                                     |
| `APP_DEBUG` desactivado                  | Expondría trazas y variables a cualquier visitante                      |
| `DB_HOST` no es `localhost`              | El accidente clásico: una variable de desarrollo que llega a producción |
| `N8N_BASE_URL` presente y no `localhost` | Igual; además, sin ella n8n nunca recibiría avisos                      |

### Falla en cerrado: el contenedor no arranca

El comando `php artisan app:check-config` sale con código 1 y la lista de problemas. La
imagen `prod` lo ejecuta **antes** del servidor:

```dockerfile
CMD ["sh", "-c", "php artisan app:check-config && exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile"]
```

Por qué no en un service provider: con FrankenPHP en modo clásico, el arranque de Laravel
ocurre **por petición**, no al levantar el proceso. Una excepción ahí deja el servidor
aceptando tráfico y respondiendo 500. Con el chequeo en el `CMD`, la plataforma ve un
contenedor que no arranca, que es una señal que sí detecta.

`exec` hace que FrankenPHP reemplace al `sh` y reciba directamente las señales de parada.

### Verificación

**Pruebas** (`tests/Feature/CheckConfigurationTest.php`): fuera de producción no exige;
configuración válida pasa; y un caso por regla (dataset de Pest). **52 pruebas, 183
aserciones.**

**Imagen `prod` real:**

| Arranque                                                   | Resultado                                                                 |
| ---------------------------------------------------------- | ------------------------------------------------------------------------- |
| `DB_HOST=127.0.0.1` y `N8N_BASE_URL=http://localhost:5678` | `running=false`, **exit 1**; el log lista los dos problemas               |
| Configuración válida                                       | `Configuración válida.` → FrankenPHP arranca → **healthy**; `/health` 200 |

---

## Fase 11 — README

**Fecha:** 14-sep-2026

- **Estado honesto por hitos.** La fundación está hecha; la API `v1`, el outbox, la IA, los
  flujos de n8n y el panel de resiliencia figuran como **pendientes**. Un README que promete
  lo que no existe es lo primero que un revisor detecta.
- **Cifras verificadas antes de escribirlas**: 52 pruebas de Pest con 183 aserciones, 2
  pruebas de Playwright `ui` y 1 de `resilience`, todas ejecutadas en esta sesión.
- **Tabla de decisiones con las alternativas descartadas** y su porqué: la sección que no se
  improvisa.
- **Pendiente para "un comando"**: el playbook pide levantarlo en un solo comando. Hoy son
  seis pasos; eso le corresponde a un `Makefile`, que se propone aparte antes de escribirlo.

La spec recibió la sección de la API pública `v1` y la regla de **solo datos ficticios** para
la IA, acordadas durante el diseño.

---

## Fase 8, segunda parte — La primera corrida del CI falló

**Fecha:** 14-sep-2026

Los dos trabajos con código fallaron en `main` (y en el PR que Dependabot abrió solo al crear
el repositorio). **Las tres causas eran del proyecto, no del runner**, y ninguna aparecía en
local porque esta máquina ya tenía lo que en un clon limpio no existe. Es el caso exacto
que el playbook describe para `verify-clean`.

Cada una se **reprodujo en local antes de arreglarla**.

### 1. `quality`: 15 pruebas con 500 — `ViteManifestNotFoundException`

Las vistas usan `@vite`; sin `public/build`, renderizar cualquier página revienta. En local
el build existía. Reproducido apartando `public/build`: **15 fallidas**, el mismo número que
en el CI.

Arreglo: `$this->withoutVite()` en `tests/TestCase.php`. **Las pruebas de backend no deben
depender del frontend compilado.** Añadir `npm run build` al trabajo de calidad también
funcionaba, pero lo haría más lento y acoplaría las dos cosas.

### 2. `e2e`: `pull access denied for reformas-leads-app`

`queue` declaraba `image: reformas-leads-app:dev` **sin `build`**. En local la imagen ya la
había construido `app`; en un runner limpio Compose intenta **descargarla** de Docker Hub.
Reproducido borrando la imagen y levantando con `--build`: mismo error.

Arreglo: la sección `build` compartida con un ancla de YAML (`x-app-build: &app-build`) y
usada por `app` y por `queue`.

### 3. `e2e`: `container reformas-queue has no healthcheck configured`

Apareció al arreglar el anterior: `docker compose up --wait` salía con **código 1** con los
cuatro servicios funcionando. La salida filtrada con `grep` no mostraba nada; **guardada
completa**, la última línea lo decía.

Era una regresión propia de la Fase 7: `HEALTHCHECK NONE` en la imagen base dejó a `queue`
sin healthcheck, y `--wait` lo trata como fallo. En su momento no se miró el código de
salida de ese `up`.

**Un primer arreglo que habría mentido.** `pgrep -f "artisan queue:listen"` parecía obvio.
Al probar la guarda en sentido contrario, buscar un proceso inexistente **también devolvía
0**: `pgrep -f` compara contra la línea de comando completa y encuentra al propio
`sh -c '...'` que contiene el texto. Habría dicho _healthy_ siempre.

Arreglo: `php artisan queue:failed`, que consulta `failed_jobs` y comprueba que el worker
**alcanza la base, que es donde vive la cola**:

| Estado         | Resultado          |
| -------------- | ------------------ |
| Base arriba    | exit 0, 325 ms     |
| Base apagada   | **exit 1**, 684 ms |
| Base de vuelta | exit 0, 319 ms     |

### Formateo que también toca YAML

`vp check --fix` reformateó `docker-compose.yml` a cuatro espacios y comillas simples (ya
estaba commiteado desde la Fase 5). Es YAML válido, pero un parche que buscaba el texto con
dos espacios dejó de coincidir. **`vp fmt` formatea YAML y Markdown, no solo TypeScript.**

### Verificación local antes de volver a subir

| Comprobación                                                                         | Resultado                                  |
| ------------------------------------------------------------------------------------ | ------------------------------------------ |
| Runner limpio simulado: imagen `dev` borrada + `docker compose up -d --build --wait` | **exit 0**, los cuatro servicios _healthy_ |
| `composer test`                                                                      | 52 pruebas, PHPStan 0 errores              |
| `npm run check`                                                                      | sin avisos                                 |

### 4. Falso positivo de la guarda de assets

Al correr la suite después de `types:check`, la guarda bloqueó sin que hubiera cambiado la
interfaz. **Los 58 archivos** más nuevos que el manifest eran exactamente los que genera
Wayfinder (41 en `actions`, 16 en `routes`, 1 en `wayfinder`): `types:check` los reescribe
con el mismo contenido y fecha nueva. El código real más reciente era anterior al build.

Primer diagnóstico **equivocado por la herramienta**: `find -printf '%TT'` ordena por hora
del día sin la fecha, y archivos de la víspera aparecían como los más recientes. Con `%T@`
(segundos desde época) salió la respuesta.

Arreglo: la guarda ignora `resources/js/{actions,routes,wayfinder}`. Verificado en los dos
sentidos: tras `types:check` **no** bloquea; tras tocar `app.tsx` **sí** bloquea. **Una guarda
que salta de más se acaba desactivando.**

| Suite end-to-end tras los arreglos | 3 pruebas en verde (17,6 s) |
| ---------------------------------- | --------------------------- |

### 5. Segunda corrida: el healthcheck de la cola exigía tablas

La segunda corrida volvió a fallar en **Start services**, esta vez con
`container reformas-queue is unhealthy`. El trabajo de calidad sí pasó, lo que confirmó en un
runner limpio el arreglo de `withoutVite()` que no se había podido verificar en local.

Los logs de Docker del artefacto lo explicaban:
`ERROR: relation "failed_jobs" does not exist`. **`queue:failed` consulta una tabla, y en el CI
la base nace vacía**: el workflow migra _después_ de `up --wait`, así que la migración nunca
llegaba a correr. En local la base ya estaba migrada. Cualquiera que clonara el repositorio y
siguiera el README habría chocado con lo mismo.

Candidatos probados sobre una **base vacía creada a propósito** y con la base apagada:

| Comando                        | Base vacía                   | Base apagada | Base real migrada             |
| ------------------------------ | ---------------------------- | ------------ | ----------------------------- |
| `queue:failed`                 | **exit 1** (reproduce el CI) | —            | exit 0                        |
| `db:show`                      | exit 0                       | exit 1       | **exit 1** en 3 de 3 intentos |
| `db:monitor --databases=pgsql` | exit 0                       | exit 1       | exit 0 en 3 de 3              |

**`db:show` habría sido otra trampa.** Pasa con la base vacía y falla con la migrada:
`The "intl" PHP extension is required to use the [format] method`. Formatea el tamaño de
las tablas con `Number::format`, que exige `intl`, y la imagen no la trae. Con la base vacía
no había tablas que formatear. **Queda anotado un hueco para más adelante:** `Number::format`
no funciona dentro del contenedor.

Arreglo: `db:monitor --databases=pgsql`, que comprueba la conexión sin consultar tablas.

**Verificado reproduciendo el orden exacto del CI**, sin tocar la base de desarrollo: base
vacía nueva, `.env` apuntando a ella (con respaldo), `up --wait` de `app` y `queue` →
**exit 0 con 0 tablas**; `migrate` → 10 tablas; `.env` restaurado con el mismo hash y base de
prueba eliminada.

---

## Fase 9 — Comandos del proyecto

**Fecha:** 14-sep-2026

`Makefile` delgado: delega en `composer`, `npm` y `docker compose`, que siguen siendo la única
fuente de verdad. Cada objetivo lleva su comentario `##` y `make help` los lista.

- **`make verify` corre lo mismo que el CI**: secretos, Pest, tipos, lint, build y end-to-end.
  Su primera ejecución **se detuvo en `lint`** por el formato de las tablas de `AGENTS.md`:
  la verificación haciendo su trabajo sobre el propio archivo que la describe
- **`make up` levanta y migra**, y lo dice en su descripción. Ningún objetivo esconde efectos
  secundarios
- **`make setup` es el único que escribe el `.env`**, y solo si no existe; genera las claves
  solo si están vacías
- **`make verify-clean` no recrea la base**: borraría los datos locales. El caso de una base
  vacía lo cubre el CI, y queda anotado en `docs/FUTURE.md`

## Convención de arquitectura

**Fecha:** 14-sep-2026

`docs/ARCHITECTURE.md`. Decisión de fondo: **el núcleo hexagonal en `src/`, los controladores
en `app/Http/Controllers`**. En `nexora-erp` los controladores viven dentro de cada contexto
porque el proyecto nació de cero; Laravel ya impone su estructura y pelearla no aporta nada.

`src/` registrado en tres sitios, porque olvidar cualquiera deja código sin revisar:
`composer.json` (`Reformas\` → `src/`), `phpstan.neon` y la sección `source` de `phpunit.xml`.

### Prueba de arquitectura, verificada en los dos sentidos

`tests/Unit/ArchitectureTest.php` con `arch()` de Pest. **Con `src/` vacío pasaría sin comprobar
nada**, así que se validó con clases temporales:

| Caso                                         | Esperado          | Resultado                                                 |
| -------------------------------------------- | ----------------- | --------------------------------------------------------- |
| `Infrastructure` usa `Illuminate` (legítimo) | pasa              | 4 de 4 en verde                                           |
| `Application` usa `Illuminate`               | falla nombrándolo | `application does not depend on the framework`            |
| `Domain` usa `Infrastructure`                | falla nombrándolo | `domain does not depend on application or infrastructure` |

**El comodín `Reformas\*\*\Domain` funciona.** Una regla sobre todo `Reformas` también detectaba
la infracción, pero **bloquearía la infraestructura legítima**: por eso se aplica por capa.

**Con `src/` vacío no pasa en silencio: rompe** con `The "src/*/*/Domain" directory does not
exist`. No hay falso verde posible, pero la prueba no puede entrar al repositorio antes que el
primer código de dominio: se commitea junto con el primer módulo.
