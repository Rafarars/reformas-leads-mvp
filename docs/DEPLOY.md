# Despliegue

Objetivo: el proyecto en internet **con coste cero** y sin dar una tarjeta, para poder enseñar la demo
desde un enlace.

## Dos caminos, los dos a coste cero

|                         | **Render + Supabase** (elegido)      | Servidor propio (VPS o VM gratuita)                     |
| ----------------------- | ------------------------------------ | ------------------------------------------------------- |
| Tarjeta                 | **No hace falta**                    | Sí, aunque sea solo para verificar                      |
| Riesgo de cargo         | **Ninguno**                          | Bajo, pero existe                                       |
| Caduca                  | No                                   | La VM gratuita de Google obliga a decidir a los 90 días |
| Nada se duerme          | ⚠️ Solo dentro de la franja con ping | ✅ Siempre                                              |
| Administrar el servidor | No                                   | Sí                                                      |

**Elegido: Render + Supabase.** Descartado Oracle porque su política comercial **no admite Venezuela**,
y Google porque exige crear una cuenta de facturación con tarjeta.

### Los tres obstáculos del plan gratuito, y cómo se resuelven

| Obstáculo                                                          | Solución                                                                                              |
| ------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------- |
| **No hay trabajadores en segundo plano** (7 $/mes)                 | La cola y el planificador corren **dentro del mismo contenedor** de la web (`docker/render/start.sh`) |
| **El disco es efímero**: se pierde lo subido y lo guardado por n8n | Archivos en **Supabase Storage** (`ATTACHMENTS_DISK=s3`) y n8n con **PostgreSQL** como almacén        |
| **El servicio se duerme a los 15 min**                             | Ping programado **en horario español**, que es cuando interesa que esté viva                          |

```
cron-job.org ──ping cada 10 min, L-V 9:00-18:00 (hora española)──► Render: app + cola + planificador
                                                                          │
                                                                          ▼
                                                            Supabase: PostgreSQL + Storage
                                                                          ▲
                                                     Render: n8n ─────────┘
```

Las 750 horas gratuitas al mes dan para **~220 h por servicio** con esa franja: caben este proyecto y
algún otro.

---

## 1. Supabase

1. Crear proyecto en `supabase.com` (gratis, sin tarjeta). Guardar la contraseña de la base.
2. **Connect → Session pooler**: de ahí salen `host`, `port`, `database` y `user`.
3. **Storage → New bucket**, privado, llamado `attachments`.
4. **Storage → S3 connection**: apuntar el _endpoint_, la región y generar las claves de acceso.

> Un proyecto gratuito **se pausa a los 7 días sin actividad**. El propio tráfico de la app lo evita;
> si va a estar parado, basta un ping semanal.

## 2. Render: la aplicación

1. Crear cuenta en `render.com` con GitHub (sin tarjeta) y **New → Blueprint** apuntando al repositorio:
   `render.yaml` ya define los dos servicios.
2. Rellenar las variables marcadas como `sync: false`:

| Variable                                      | De dónde sale                                                                           |
| --------------------------------------------- | --------------------------------------------------------------------------------------- |
| `APP_KEY`                                     | `php artisan key:generate --show`                                                       |
| `APP_URL`                                     | La URL que da Render a la app                                                           |
| `DB_*`                                        | Supabase → Session pooler                                                               |
| `AWS_*`                                       | Supabase → Storage → S3 connection                                                      |
| `GEMINI_API_KEY`                              | Google AI Studio                                                                        |
| `ADMIN_EMAIL` y `ADMIN_PASSWORD`              | Tu cuenta del panel: se crea al arrancar (`app:admin:ensure`). Sin ellas no hay usuario |
| `DELIVERY_TEAM_URL` y `DELIVERY_CUSTOMER_URL` | `https://<n8n en Render>/webhook/reformas-events`                                       |
| `DELIVERY_CRM_URL`                            | La URL del CRM simulado (`reformas-fake-crm`) más `/webhooks/leads`                     |
| `TELEGRAM_BOT_USERNAME`                       | El nombre del bot, sin la arroba. Sin él, la pantalla de Ajustes no pinta el enlace     |
| `BOT_API_KEY`                                 | `echo "rl_$(openssl rand -hex 32)"`. La misma clave va en la credencial de n8n          |
| `FAKE_CRM_URL` y `FAKE_CRM_TOKEN`             | La URL de `reformas-fake-crm` y su token (`openssl rand -hex 32`), el mismo en los dos  |

`start.sh` ejecuta `app:check-config` y las migraciones antes de servir tráfico: con la configuración a
medias, el servicio no arranca. Después, dos comandos idempotentes: `app:admin:ensure` crea al
administrador, y `app:api-client:ensure-bot` deja lista la clave con la que el bot llama a
`POST /api/v1/telegram/link`. Esto último existe porque **el plan gratuito de Render no da consola**: sin
ello no habría forma de crear esa clave en producción. Si `BOT_API_KEY` no está puesta, no falla el
arranque: lo dice y sigue.

**Tropiezos del primer despliegue:** `frankenphp: Operation not permitted`. El binario trae la capability
`cap_net_bind_service` (para escuchar en el 443) y el entorno aislado de Render no deja ejecutar
binarios con capabilities sin ser root. La etapa `render` del Dockerfile —la última, que es la que
construyen las plataformas que no dejan elegir target— copia el binario para quitársela. Allí no hace
falta: el puerto es alto.

2. **n8n se quedaba sin memoria** en los 512 MB del plan gratuito, y costó tres intentos dar con el porqué.
   Faltaba `DB_POSTGRESDB_SSL_ENABLED=true` —Supabase exige TLS y la conexión se colgaba— y el primer
   arranque sobre una base vacía no cabe, así que las ~200 migraciones se hicieron una vez desde una
   máquina con memoria. Pero lo que de verdad lo mataba eran otras dos cosas: n8n 2.x arranca **un segundo
   proceso de Node** (`N8N_RUNNERS_ENABLED=false` lo quita) y **servir el editor** no cabe
   (`N8N_DISABLE_UI=true`). Con eso se queda plano en ~300 MB. Medirlo exige capar el contenedor de verdad
   (`docker run --memory=512m`); el razonamiento completo está en
   [`DEPLOY-BITACORA.md`](DEPLOY-BITACORA.md) §6.

3. **Sin usuario para entrar:** el usuario de prueba de local (`test@example.com`) no existe en producción
   ni debe existir: tiene una contraseña conocida. El administrador se crea al arrancar desde
   `ADMIN_EMAIL` y `ADMIN_PASSWORD`, y **el registro público está cerrado en producción**
   (`REGISTRATION_ENABLED`): con él abierto, cualquiera entraría al panel y podría aprobar correos que
   salen de tu Gmail. El panel no distingue roles: quien entra puede todo.

4. **Página en blanco tras desplegar:** Render termina el TLS y reenvía por `http`, así que Laravel
   generaba las URLs de los assets con `http` y el navegador las bloqueaba por contenido mixto. Arreglo:
   `trustProxies(at: '*')` en `bootstrap/app.php`.

## 2 bis. Render: el CRM simulado

La demo de resiliencia necesita un CRM al que se le pueda **tumbar y levantar** desde el panel. En local
es el contenedor `fake-crm`; en producción, el servicio `reformas-fake-crm` del mismo blueprint: un solo
archivo de Node sin dependencias (`rootDir: docker/fake-crm`, para que Render no instale el frontend ni
lo redespliegue con cada commit).

1. Generar un token: `openssl rand -hex 32`.
2. `reformas-fake-crm` → `FAKE_CRM_TOKEN` = ese token. **Sin él no arranca**, a propósito: expone los
   controles y la lista de leads recibidos, y no puede quedar abierto en internet.
3. `reformas-leads` → `FAKE_CRM_TOKEN` (el mismo), `FAKE_CRM_URL` (la URL del servicio) y
   `DELIVERY_CRM_URL` (esa URL más `/webhooks/leads`).

Solo `/health` y `/webhooks/leads` responden sin token. Si `FAKE_CRM_URL` está puesta y falta el token,
la app no arranca (`app:check-config`); si no está puesta, el panel muestra el CRM como inalcanzable y
todo lo demás funciona. Duerme como los otros servicios: el primer aviso tras despertarlo puede fallar y
se reintenta, que es justo lo que la demo enseña.

## 3. Render: n8n

El n8n de Render **solo ejecuta**: recibe el webhook y dispara Telegram y Gmail. No sirve el editor, porque
eso no cabe en 512 MB. **Se administra desde otra instancia de n8n contra la misma base de Supabase**, que
es lo que el propio n8n recomienda para producción. El estado vive en la base, así que apagar la máquina
desde la que se edita **no afecta al servicio**.

1. El mismo blueprint crea el servicio `reformas-n8n` con la imagen oficial.
2. Variables: las `DB_POSTGRESDB_*` de Supabase (esquema `n8n`), `N8N_ENCRYPTION_KEY`
   (`openssl rand -hex 32`), y `N8N_HOST` y `WEBHOOK_URL` con la URL que le dé Render.
3. **Migrar la base una vez** desde una máquina con memoria, con esas mismas variables y la **misma**
   `N8N_ENCRYPTION_KEY`. Cuando el log diga _"Editor is now accessible"_, **apagar el contenedor**: si se
   queda vivo consume conexiones del pooler.
4. Comprobar que responde: `https://<n8n en Render>/healthz` → 200.
5. Levantar n8n en local **contra esa misma base** y, desde ahí:
    - **Rehacer la credencial de Gmail**: la URI de redirección pasa a
      `https://<n8n en Render>/rest/oauth2-credential/callback`; hay que añadirla en Google Cloud.
    - Crear la credencial de Telegram con el token del bot.
    - Importar el flujo (`n8n/workflows/reformas-avisos.json`), poner el `chat_id` real y **publicarlo**.
    - Apagar ese n8n local al terminar.

## 4. Mantenerlo despierto

En `cron-job.org` (gratis), un trabajo que pida `https://<app>/health`:

- cada **10 minutos**, de **lunes a viernes**, de **9:00 a 18:00 hora española**;
- fuera de esa franja se duerme, y la primera visita tarda ~1 minuto en despertarlo.

Un lead que entre de madrugada **se recibe igual** (la petición despierta el servicio); su clasificación
y sus avisos salen en cuanto la cola vuelve a correr. Es el comportamiento que el outbox garantiza.

## 5. Comprobar que quedó bien

1. `https://<app>/health` responde `ok`.
2. Registrar un lead y verlo clasificado: la cola funciona.
3. Aprobar un borrador: llega el correo. La primera vez puede tardar si n8n estaba dormido.
4. Subir una foto y volver a abrirla tras un reinicio del servicio: sigue ahí, porque vive en Supabase.

## 6. Antes de enseñarlo a nadie

| Riesgo                    | Qué hacer                                                                          |
| ------------------------- | ---------------------------------------------------------------------------------- |
| El editor de n8n expuesto | No se sirve: `N8N_DISABLE_UI=true`. Se administra desde local contra la misma base |
| Datos reales              | **Solo datos ficticios**: el tier gratuito de la IA puede usarlos para entrenar    |
| La API pública            | Revisar `API_REQUESTS_PER_MINUTE`                                                  |
| Pérdida de datos          | Supabase hace copias; exportar el flujo de n8n al repositorio tras cada cambio     |

## 7. Rotar la clave de cifrado de n8n

`N8N_ENCRYPTION_KEY` es la clave con la que n8n cifra **las credenciales guardadas** (el token del bot de
Telegram y la autorización de Gmail). Los flujos no van cifrados. Si la clave se filtra, `AGENTS.md` manda
rotarla, no esconderla.

**Cambiar solo la variable en Render rompe los avisos**: las credenciales siguen cifradas con la clave
vieja y n8n ya no puede leerlas. Como el panel solo mira `/healthz`, el servicio se vería sano mientras
Telegram y Gmail dejan de salir. Hay que **recifrarlas**, y para eso se necesitan las dos claves.

Se hace desde una máquina con memoria, contra la misma base de Supabase, con el n8n de Render corriendo
como está (no hace falta pararlo; el corte dura lo que tarde el paso 4).

1. **Exportar las credenciales descifradas con la clave vieja.** El archivo sale en claro: se guarda
   fuera del repositorio y se borra en el paso 5.

    ```bash
    mkdir -p ~/n8n-rotacion && cd ~/n8n-rotacion
    docker run --rm -v "$PWD:/backup" \
      -e DB_TYPE=postgresdb -e DB_POSTGRESDB_HOST=<pooler de Supabase> -e DB_POSTGRESDB_PORT=5432 \
      -e DB_POSTGRESDB_DATABASE=postgres -e DB_POSTGRESDB_USER=<usuario> -e DB_POSTGRESDB_PASSWORD="$SUPABASE_DB_PASSWORD" \
      -e DB_POSTGRESDB_SCHEMA=n8n -e DB_POSTGRESDB_SSL_ENABLED=true -e DB_POSTGRESDB_SSL_REJECT_UNAUTHORIZED=false \
      -e N8N_ENCRYPTION_KEY="$CLAVE_VIEJA" \
      n8nio/n8n:2.38.7 export:credentials --all --decrypted --output=/backup/credenciales.json
    ```

2. **Generar la clave nueva**: `openssl rand -hex 32`. No se pega en ningún chat.

3. **Importar con la clave nueva**, que las vuelve a cifrar en la misma base: el mismo `docker run`, con
   `N8N_ENCRYPTION_KEY="$CLAVE_NUEVA"` y `import:credentials --input=/backup/credenciales.json`.

4. **Poner la clave nueva en Render** (`reformas-n8n` → Environment → `N8N_ENCRYPTION_KEY`) y en el `.env`
   local (`RENDER_N8N_ENCRYPTION_KEY`, que solo usa el contenedor temporal). Render reinicia el servicio.

5. **Borrar el archivo en claro**: `shred -u ~/n8n-rotacion/credenciales.json`.

6. **Comprobar de punta a punta**, que es lo único que demuestra que el recifrado salió bien:
   `https://<n8n en Render>/healthz` → 200, registrar un lead y ver el aviso en Telegram, y aprobar un
   borrador y ver llegar el correo. Si algo falla, las credenciales se rehacen a mano desde el n8n local
   contra la misma base, como en el paso 3 de la sección 3.

---

# Alternativa: servidor propio

Si algún día hay presupuesto o una VM gratuita disponible, `compose.prod.yml` levanta **todo** en una
máquina (app, cola, planificador, base, n8n y el CRM simulado) sin nada que se duerma.

## 1. La máquina

1. Crear cuenta en `oracle.com/cloud/free`. Pide una tarjeta **solo para verificar**: retiene 1 $ y
   lo devuelve. Rechaza a menudo tarjetas prepago o virtuales.
2. **Región de origen**: elegir una grande y cercana (por ejemplo Madrid o Fráncfort). **No se puede
   cambiar después** y determina si hay máquinas ARM libres.
3. Crear una instancia:
    - Imagen **Ubuntu 24.04**, forma **VM.Standard.A1.Flex** con **2 OCPU y 12 GB**.
    - Guardar la clave SSH que ofrece al crearla.
    - Si responde _"Out of capacity"_, reintentar más tarde o en otro dominio de disponibilidad.
4. Abrir puertos 80 y 443 en **dos sitios**, o no entra el tráfico:
    - la **lista de seguridad** de la subred, en la consola de Oracle;
    - el **cortafuegos de la propia máquina** (`iptables` en las imágenes de Oracle).

```bash
sudo iptables -I INPUT 1 -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 1 -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

## 2. Dominio

Hace falta un dominio para el certificado HTTPS. Sirve uno gratuito (DuckDNS, por ejemplo) con dos
nombres apuntando a la IP pública:

| Nombre               | Para qué         |
| -------------------- | ---------------- |
| `reformas.<dominio>` | La aplicación    |
| `n8n.<dominio>`      | El editor de n8n |

## 3. Preparar el servidor

```bash
sudo apt update && sudo apt install -y docker.io docker-compose-plugin git
sudo usermod -aG docker $USER   # cerrar sesión y volver a entrar
git clone <repositorio> reformas-leads-mvp && cd reformas-leads-mvp
```

## 4. Secretos

Crear `.env.prod` en el servidor. **Nunca se sube al repositorio.**

```bash
APP_KEY=            # php artisan key:generate --show
APP_URL=https://reformas.<dominio>
APP_DOMAIN=reformas.<dominio>
DB_PASSWORD=        # openssl rand -hex 24
N8N_ENCRYPTION_KEY= # openssl rand -hex 32
N8N_DOMAIN=n8n.<dominio>
GEMINI_API_KEY=
DELIVERY_CRM_URL=http://fake-crm:8080/webhooks/leads
```

`app:check-config` **impide arrancar** si algo falta o apunta a `localhost`: el contenedor no acepta
tráfico con una configuración a medias.

## 5. Levantar

```bash
docker compose -f compose.prod.yml --env-file .env.prod up -d --build
docker compose -f compose.prod.yml --env-file .env.prod exec -T app php artisan migrate --force
docker compose -f compose.prod.yml --env-file .env.prod exec -T app php artisan db:seed --force
```

FrankenPHP pide el certificado a Let's Encrypt solo, a partir de `SERVER_NAME`. Requisito: que el
dominio ya apunte a la IP y que el puerto 80 esté abierto.

## 6. n8n en producción

1. Publicar el editor: mientras se configura, abrir un túnel SSH en vez de exponerlo a internet.

    ```bash
    ssh -L 5678:localhost:5678 ubuntu@<ip>
    ```

2. **Rehacer la credencial de Gmail**: la URI de redirección cambia a
   `https://n8n.<dominio>/rest/oauth2-credential/callback`. Hay que añadirla en Google Cloud y volver
   a conectar la cuenta.
3. Importar el flujo y publicarlo:

    ```bash
    docker compose -f compose.prod.yml cp n8n/workflows/reformas-avisos.json n8n:/tmp/avisos.json
    docker compose -f compose.prod.yml exec n8n n8n import:workflow --input=/tmp/avisos.json
    ```

4. Poner el `chat_id` real en el nodo de Telegram y seleccionar las credenciales.

## 7. Antes de enseñarlo a nadie

| Riesgo                                      | Qué hacer                                                                       |
| ------------------------------------------- | ------------------------------------------------------------------------------- |
| El `/control` del CRM simulado está abierto | No publicarlo, o protegerlo: cualquiera podría provocar caídas                  |
| El editor de n8n expuesto                   | Túnel SSH, o usuario y contraseña delante                                       |
| La API pública sin límite real              | Ya hay límite por cliente; revisar el valor de `API_REQUESTS_PER_MINUTE`        |
| Datos reales                                | **Solo datos ficticios**: el tier gratuito de la IA puede usarlos para entrenar |
| Pérdida de datos                            | Copia diaria de la base y del volumen de n8n                                    |

Copia de seguridad mínima:

```bash
docker compose -f compose.prod.yml exec -T db pg_dump -U reformas reformas | gzip > backup-$(date +%F).sql.gz
```

## 8. Comprobar que quedó bien

1. `https://reformas.<dominio>/health` responde `ok` y con la base caída responde 503.
2. Registrar un lead y verlo clasificado en unos segundos: la cola funciona.
3. Aprobar un borrador: llega el correo.
4. Panel de Resiliencia: tumbar el CRM, inyectar leads y ver cómo se drena la cola al restablecerlo.

## Alternativa sin tarjeta

Si Oracle rechaza la verificación: **Render + Supabase**, asumiendo recortes.

- **Base**: Supabase (500 MB). Un ping semanal evita que se pause.
- **App y cola en el mismo servicio web**: en vez de pagar el _worker_, el contenedor arranca
  FrankenPHP y `queue:work` juntos.
- **Un ping cada 10 minutos** (cron-job.org) evita que la app se duerma. Render regala 750 horas al
  mes por cuenta: llega para mantener **una** sola cosa despierta.
- **n8n se dormirá**: el primer aviso falla mientras arranca y se entrega en el reintento. Es una
  demostración honesta del outbox, pero hay que contarlo.
