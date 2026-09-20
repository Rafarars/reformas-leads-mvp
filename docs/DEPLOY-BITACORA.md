# Bitácora del despliegue

Qué se decidió, por qué, qué falló y en qué punto está. La guía de pasos está en
[`DEPLOY.md`](DEPLOY.md); esto es la historia y el razonamiento.

**Fecha:** 19 y 20-sep-2026 · **Estado al cerrar:** app viva en Render; **n8n sin arrancar** (ver §6).

---

## 1. La restricción que lo gobierna

**Coste cero y sin dar una tarjeta.** Rafael no tiene presupuesto para el despliegue y prefiere no exponer
su tarjeta. Todo lo demás se decidió a partir de ahí.

## 2. Las alternativas y por qué se descartaron

El esquema inicial que estaba anotado en `docs/FUTURE.md` era **base en Supabase, app y n8n en Render**.
Se revisaron las condiciones vigentes de cada plan (septiembre de 2026) antes de tocar nada:

| Opción                                               | Coste                 | Veredicto        | Por qué                                                                                                                                                                                                                                                                                                                               |
| ---------------------------------------------------- | --------------------- | ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Render + Supabase**                                | 0 €                   | ✅ **Elegida**   | No pide tarjeta, no caduca, y es posible resolver sus tres límites (ver §3)                                                                                                                                                                                                                                                           |
| **Oracle Cloud Always Free** (VM ARM 2 vCPU y 12 GB) | 0 €                   | ❌ **Imposible** | Su política de cumplimiento comercial **prohíbe el acceso a nacionales de Venezuela**, entre otros. Una VPN no lo arregla: la cuenta se puede cerrar al detectarlo y se pierde todo sin aviso. Además rechaza tarjetas prepago o virtuales                                                                                            |
| **Google Cloud e2-micro** (1 GB de RAM)              | 0 € dentro de límites | ❌ Descartada    | Exige **crear una cuenta de facturación con tarjeta**. La prueba de 90 días no cobra sola (solo cobra si se pulsa "Actualizar"), pero las alertas de presupuesto **avisan, no frenan**; depende de no equivocarse (región, disco SSD, tráfico). La cuenta de Rafael no tenía ninguna cuenta de facturación (comprobado en la consola) |
| **Hetzner CX22** (2 vCPU, 4 GB)                      | ~4,50 €/mes           | ❌ Descartada    | No es gratis                                                                                                                                                                                                                                                                                                                          |
| **Fly.io**                                           | 8-25 $/mes            | ❌ Descartada    | Ya no tiene plan gratuito para cuentas nuevas                                                                                                                                                                                                                                                                                         |
| **Render con worker separado**                       | 7 $/mes               | ❌ Descartada    | El plan gratuito **no incluye trabajadores en segundo plano**                                                                                                                                                                                                                                                                         |

**Nota sobre la región:** en Google la capa gratuita solo cubre máquinas creadas en `us-west1`,
`us-central1` o `us-east1`. Es dónde vive la máquina, **no desde dónde te conectas**: una VPN activa no
tiene nada que ver.

## 3. Los tres límites del plan gratuito de Render y cómo se resolvieron

| Límite                                                                                  | Solución                                                                                                                                                                | Dónde                                |
| --------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------ |
| **Sin worker en segundo plano** (la cola y el planificador son el corazón del proyecto) | La cola y el planificador corren **dentro del contenedor web**, cada uno en un bucle que lo reinicia si muere                                                           | `docker/render/start.sh`             |
| **Disco efímero**: se pierde lo subido y lo que n8n guarda en disco                     | Archivos a **Supabase Storage** por S3 (`ATTACHMENTS_DISK=s3`, un cambio de variable gracias al puerto `AttachmentStorage`) y **n8n con PostgreSQL** como almacén       | `render.yaml`, `config/services.php` |
| **Se duerme a los 15 minutos** (≈1 minuto en despertar)                                 | Ping programado **en horario español** (el que interesa si el puesto es en España), con cron-job.org. Las 750 h gratuitas al mes dan ~220 h por servicio con esa franja | pendiente de configurar              |

**Sobre "meter la cola en el mismo contenedor":** resuelve el coste del _worker_, **no** evita que se
duerma. Render suspende la instancia entera tras 15 minutos sin tráfico HTTP, con todo lo que haya dentro.

## 4. Decisiones de proceso

- **Rama `production`.** Render despliega en cada push **sin esperar al CI**; desplegar desde `main`
  publicaría un fallo. Flujo: `main` (CI verde) → `git merge main` en `production` → Render despliega.
  Se descartó una rama `qa` aparte: con una persona, `main` ya hace de QA con 396 pruebas y E2E contra
  contenedores reales.
- **Repositorio privado:** Render pide acceso por GitHub; se le dio **solo a este repositorio**, no a
  todos.
- **Supabase solo como PostgreSQL y almacén de archivos:** _Data API_ y _exposición automática de tablas_
  **desactivadas**. Con la API abierta, cualquiera con la URL del proyecto podría leer los leads sin pasar
  por la app (saltándose la API key, el límite y la sesión).
- **Bucket privado**, sin límite de tamaño ni de tipos MIME en el almacén: esas reglas viven en el
  dominio (`AttachmentSize`, `AttachmentContentType`), en un solo sitio.
- **CRM simulado sin desplegar:** exponer sus controles de caída en internet no tiene sentido.
  `DELIVERY_CRM_URL` apunta a `https://httpbin.org/post`, que responde 200 a todo.
- **Sin usuario de prueba en producción:** `test@example.com` tiene una contraseña conocida; cualquiera
  entraría al panel y aprobaría correos desde el Gmail conectado. El administrador se crea al arrancar
  desde `ADMIN_EMAIL` y `ADMIN_PASSWORD` (`app:admin:ensure`) y **el registro público está cerrado en
  producción** (`REGISTRATION_ENABLED`). La app no tiene roles: quien entra puede todo.
- **Gemini se mantiene** como modelo de la app, aunque Groq da más peticiones (30/min y 1.000/día frente a
  15/min y 500/día): está probado y afinado, y 500 al día sobran para una demo. Groq queda para después,
  detrás del puerto `LeadClassifier`.

## 5. Cronología y tropiezos

| #   | Síntoma                                                        | Causa                                                                                                                                                                  | Arreglo                                                                                                                                                    |
| --- | -------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | `frankenphp: Operation not permitted` (estado 126)             | El binario trae la _capability_ `cap_net_bind_service` (para el puerto 443) y el entorno aislado de Render **no deja ejecutar binarios con capabilities sin ser root** | Etapa `render` **al final** del Dockerfile (la que construyen las plataformas que no dejan elegir target) que copia el binario para quitársela             |
| 2   | Página en blanco al cargar                                     | Render termina el TLS y reenvía por `http`: Laravel generaba los assets con `http://` y el navegador los bloqueaba (contenido mixto)                                   | `trustProxies(at: '*')` en `bootstrap/app.php`; además `APP_NAME`                                                                                          |
| 3   | El escaneo de secretos falló en el CI                          | (a) `- key: NOMBRE` de `render.yaml` se toma por secreto (`generic-api-key`); (b) contraseñas inventadas de una prueba                                                 | `.gitleaks.toml` con `regexTarget = "match"` y una lista de rutas. **Esta versión de gitleaks solo admite un `[allowlist]`**: `[[allowlists]]` no funciona |
| 4   | El CI "pasaba" en local y fallaba en remoto                    | `make secrets` escanea el **historial**, no lo no commiteado                                                                                                           | Pasarlo **después** del commit y antes del push                                                                                                            |
| 5   | Un commit con hallazgos llegó a `origin`                       | Un script encadenado con `;` siguió tras un fallo                                                                                                                      | Encadenar con `&&` y condicionar el `push` a que el escaneo pase                                                                                           |
| 6   | **n8n: `JavaScript heap out of memory` en bucle** (estado 134) | Ver §6                                                                                                                                                                 | **Sin resolver**                                                                                                                                           |

## 6. El problema abierto: n8n no arranca en Render

**Síntoma.** Peticiones a `https://reformas-n8n.onrender.com` dan 502 o se quedan cargando. Los registros
muestran siempre lo mismo:

```
n8n ready on ::, port 10000
Acquiring database migration lock...
Database ping failed (1/3): Database connection timed out
FATAL ERROR: Ineffective mark-compacts near heap limit — JavaScript heap out of memory
```

Render reinicia y vuelve a empezar. Rafael recibe correos de _"exceeded its memory limit"_ y _"Exited with
status 134"_.

**Lo que se descartó** (con prueba):

- **No es la conexión.** Se arrancó n8n 2.38.7 en local contra el pooler de Supabase con una contraseña
  falsa: responde al instante con _"password authentication failed"_. Red, TLS y usuario **funcionan**.
- **No son los valores.** Se revisaron en el panel de Render, uno a uno (sin abrir la contraseña): host
  `aws-0-us-west-2.pooler.supabase.com`, puerto `5432`, usuario `postgres.<id>`, esquema `n8n`,
  `SSL_ENABLED=true`, `POOL_SIZE=2`. Todos correctos.
- **No es el tráfico.** El servicio se cae solo, sin peticiones.

**Hipótesis vigente.** Sobre una base **vacía**, n8n ejecuta unas doscientas migraciones de golpe en el
primer arranque. Eso necesita más de los 512 MB del plan gratuito, se aborta por memoria y, al reiniciar,
**empieza desde cero**: nunca termina. La línea `Acquiring database migration lock...` sin nada después
encaja con esto.

**Intentos ya hechos** (no bastaron): `NODE_OPTIONS=--max-old-space-size` a 350 y luego 256, sin
_task runners_, sin plantillas ni avisos de versión, `N8N_CONCURRENCY_PRODUCTION_LIMIT=2`,
`EXECUTIONS_DATA_SAVE_ON_SUCCESS=none` y poda del historial.

**Plan.** Hacer esa migración **una sola vez desde la máquina de Rafael**, que tiene memoria de sobra,
contra la misma base y el mismo esquema:

```bash
docker run --rm \
  -e DB_TYPE=postgresdb -e DB_POSTGRESDB_HOST=aws-0-us-west-2.pooler.supabase.com -e DB_POSTGRESDB_PORT=5432 \
  -e DB_POSTGRESDB_DATABASE=postgres -e DB_POSTGRESDB_USER=postgres.<id> -e DB_POSTGRESDB_PASSWORD="$SUPABASE_DB_PASSWORD" \
  -e DB_POSTGRESDB_SCHEMA=n8n -e DB_POSTGRESDB_SSL_ENABLED=true -e DB_POSTGRESDB_SSL_REJECT_UNAUTHORIZED=false \
  -e N8N_ENCRYPTION_KEY="$N8N_ENCRYPTION_KEY" -e N8N_PORT=5680 \
  n8nio/n8n:2.38.7
```

Cuando el registro diga _"Editor is now accessible"_, se detiene. **La `N8N_ENCRYPTION_KEY` debe ser la
misma que la de Render**, o las credenciales guardadas no se podrán leer. Después el n8n de Render solo
tiene que arrancar sobre un esquema ya migrado.

**Si no funciona**, alternativas en orden: (1) probar con `DB_POSTGRESDB_POOL_SIZE=1` y menos
`--max-old-space-size`; (2) SQLite **con disco persistente**, que Render solo da en planes de pago;
(3) n8n en otro sitio gratuito (una VM que no sea Oracle) y solo la app en Render; (4) dejar n8n solo en
local y desplegar la app con los avisos apuntando a una URL pública temporal.

## 7. Estado exacto al cerrar

| Pieza                                                                                            | Estado                                                                                                                                    |
| ------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------- |
| Rama `production`                                                                                | En `230596a`, igual que `main`, CI en verde                                                                                               |
| App `reformas-leads`                                                                             | ✅ Viva. `https://reformas-leads.onrender.com/health` → `{"status":"ok","database":"up"}`. Migraciones aplicadas en Supabase              |
| Login de la app                                                                                  | ⏳ Faltan `ADMIN_EMAIL` y `ADMIN_PASSWORD` en Render (Rafael)                                                                             |
| Archivos (Supabase Storage)                                                                      | Preparado; sin probar en producción                                                                                                       |
| **n8n `reformas-n8n`**                                                                           | ❌ **Caído** (§6)                                                                                                                         |
| Credenciales de Telegram y Gmail en el n8n de producción                                         | ⏳ Depende de que n8n arranque. La URI de redirección de Gmail pasa a `https://reformas-n8n.onrender.com/rest/oauth2-credential/callback` |
| Importar `n8n/workflows/reformas-avisos.json`                                                    | ⏳ Igual                                                                                                                                  |
| Ping por horario (cron-job.org)                                                                  | ⏳                                                                                                                                        |
| Servicio viejo `n8n` en Render (proyecto `n8n_selfhost_prueba`, de hace un año, imagen `latest`) | Para borrar cuando el nuevo funcione                                                                                                      |

Tras el despliegue quedan: miniatura de las fotos en el panel (pedida por Rafael), Groq detrás de
`LeadClassifier`, repositorio público, vídeo y revocar el token del bot antiguo de Telegram.
