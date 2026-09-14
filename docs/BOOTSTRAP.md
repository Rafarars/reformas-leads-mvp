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
