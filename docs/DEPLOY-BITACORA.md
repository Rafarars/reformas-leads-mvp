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

## 6. n8n no arrancaba en Render (resuelto)

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

**Primera hipótesis** (resultó ser solo una parte). Sobre una base **vacía**, n8n ejecuta unas doscientas migraciones de golpe en el
primer arranque. Eso necesita más de los 512 MB del plan gratuito, se aborta por memoria y, al reiniciar,
**empieza desde cero**: nunca termina. La línea `Acquiring database migration lock...` sin nada después
encaja con esto.

**Intentos ya hechos** (no bastaron): `NODE_OPTIONS=--max-old-space-size` a 350 y luego 256, sin plantillas
ni avisos de versión, `N8N_CONCURRENCY_PRODUCTION_LIMIT=2`, `EXECUTIONS_DATA_SAVE_ON_SUCCESS=none` y poda
del historial. Ninguno tocaba las dos causas reales (§6.3).

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

La migración **funcionó**: el esquema `n8n` quedó creado en Supabase. Pero Render **siguió cayendo**, así
que la hipótesis de las migraciones era, como mucho, la mitad de la historia.

### 6.1. El veredicto equivocado, y por qué lo era

En una sesión aparte se midió el consumo de n8n en local con `docker stats`, se vio un pico de **798 MB**
y se concluyó que n8n 2.38.7 es **imposible** en 512 MB. La conclusión era falsa, y las tres razones valen
para cualquier medición de memoria:

- **Medir en una máquina con 16 GB no dice nada.** Sin `--max-old-space-size`, V8 dimensiona el montón
  según la memoria **del sistema**: deja crecer la basura hasta un par de gigas antes de recogerla. El pico
  de 798 MB solo significaba que **nadie le pidió contenerse**.
- **La memoria residente incluye páginas de fichero** (el binario de Node, el `node_modules` enorme) que el
  núcleo **descarta bajo presión** en vez de provocar un fallo. 669 MB sin límite **no** equivale a 669 MB
  con límite.
- **`Database ping failed: connection timed out` no es un síntoma de memoria.** Sale también en local
  contra Supabase, con memoria de sobra. Es un tic del pooler.

**La única prueba válida es reproducir el límite**: `docker run --memory=512m --memory-swap=512m`.

### 6.2. Lo que dijo esa prueba

Cuatro arranques contra la base ya migrada, todos con el contenedor capado de verdad a 512 MB:

| Configuración                             | Resultado                                                                           |
| ----------------------------------------- | ----------------------------------------------------------------------------------- |
| montón 352, ejecutor de tareas activo     | muere a los 27 s, salida **137** (lo mata el núcleo)                                |
| montón 352, sin ejecutor                  | arranca en 9 s con 234 MB, `/healthz` 200; muere al abrir el editor (137)           |
| montón 256, sin ejecutor                  | arranca con 311 MB, `/healthz` 200; muere al abrir el editor (**134**, se ahoga V8) |
| **montón 320, sin ejecutor y sin editor** | **estable: 300 peticiones de webhook, 296-310 MB planos, cero reinicios**           |

### 6.3. Las dos causas reales

1. **Un segundo proceso de Node.** n8n 2.x arranca por defecto un _JS Task Runner_ aparte, con su
   _broker_. Dos runtimes de Node no caben en 512 MB; la última línea antes de morir era
   `Registered runner "JS Task Runner"`. Con `N8N_RUNNERS_ENABLED=false` el código vuelve a ejecutarse en
   el proceso principal, como en n8n 1.x. **El flujo no usa nodos `Code`** (webhook, switch, telegram,
   gmail y stop-and-error), así que no se pierde nada.
2. **El editor.** Servir la interfaz revienta el contenedor por los dos lados: con el montón alto lo mata
   el núcleo por memoria residente, con el montón bajo se ahoga V8. Con `N8N_DISABLE_UI=true` el problema
   desaparece y los webhooks siguen igual.

**Y un tiro en el pie propio:** `NODE_OPTIONS=--max-old-space-size=256` estaba en `render.yaml` desde el
principio, puesto por nosotros creyendo que ayudaba. Ese valor es **peor que no poner nada**. Render nunca
tuvo una oportunidad justa.

### 6.4. Cómo queda

n8n en Render **solo ejecuta**: recibe el webhook y dispara Telegram y Gmail. Para **editar** el flujo o
crear credenciales se levanta n8n en local apuntando a **la misma base de Supabase**, donde el editor tiene
toda la memoria que quiera. Es la separación que el propio n8n recomienda para producción.

Que el portátil esté apagado **no afecta al servicio**: el estado vive en Supabase y quien ejecuta es
Render. El portátil es el teclado, no el servidor. Eso lo distingue de la alternativa descartada de correr
n8n en casa con un túnel, donde apagar el equipo sí tumba el servicio.

### 6.5. Cabo suelto que apareció

El contenedor temporal de la migración quedó **seis horas encendido**, comiéndose conexiones del pooler de
Supabase, que en el plan gratuito son pocas. **Apagarlo siempre en cuanto termine de migrar.**

## 7. Estado exacto al cerrar

| Pieza                                                                                            | Estado                                                                                                                                                                          |
| ------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Rama `production`                                                                                | En `230596a`, igual que `main`, CI en verde                                                                                                                                     |
| App `reformas-leads`                                                                             | ✅ Viva. `https://reformas-leads.onrender.com/health` → `{"status":"ok","database":"up"}`. Migraciones aplicadas en Supabase                                                    |
| Login de la app                                                                                  | ⏳ Faltan `ADMIN_EMAIL` y `ADMIN_PASSWORD` en Render (Rafael)                                                                                                                   |
| Archivos (Supabase Storage)                                                                      | Preparado; sin probar en producción                                                                                                                                             |
| Base de n8n en Supabase (esquema `n8n`)                                                          | ✅ Migrada desde la máquina de Rafael. **No repetir**                                                                                                                           |
| **n8n `reformas-n8n`**                                                                           | ⏳ Causa encontrada y probada en local (§6). Falta aplicar las tres variables en Render y verificar `/healthz`                                                                  |
| Credenciales de Telegram y Gmail                                                                 | ⏳ Se crean desde el n8n **local** contra la base de Supabase (§6.4). La URI de redirección de Gmail pasa a `https://reformas-n8n.onrender.com/rest/oauth2-credential/callback` |
| Importar `n8n/workflows/reformas-avisos.json`                                                    | ⏳ Igual, desde el n8n local                                                                                                                                                    |
| Ping por horario (cron-job.org)                                                                  | ⏳                                                                                                                                                                              |
| Servicio viejo `n8n` en Render (proyecto `n8n_selfhost_prueba`, de hace un año, imagen `latest`) | Para borrar cuando el nuevo funcione                                                                                                                                            |

Tras el despliegue quedan: miniatura de las fotos en el panel (pedida por Rafael), Groq detrás de
`LeadClassifier`, repositorio público, vídeo y revocar el token del bot antiguo de Telegram.
