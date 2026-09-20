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
| `DELIVERY_CRM_URL`                            | Sin CRM desplegado, cualquier URL que falle sirve: demuestra los reintentos             |

`start.sh` ejecuta `app:check-config` y las migraciones antes de servir tráfico: con la configuración a
medias, el servicio no arranca.

**Tropiezos del primer despliegue:** `frankenphp: Operation not permitted`. El binario trae la capability
`cap_net_bind_service` (para escuchar en el 443) y el entorno aislado de Render no deja ejecutar
binarios con capabilities sin ser root. La etapa `render` del Dockerfile —la última, que es la que
construyen las plataformas que no dejan elegir target— copia el binario para quitársela. Allí no hace
falta: el puerto es alto.

2. **n8n se quedaba sin memoria** (`JavaScript heap out of memory`) en los 512 MB del plan gratuito. Dos
   causas: faltaba `DB_POSTGRESDB_SSL_ENABLED=true` —Supabase exige TLS y la conexión se colgaba hasta
   agotar el tiempo— y n8n arrancaba piezas que no usamos. Se añadieron tope de montón
   (`NODE_OPTIONS=--max-old-space-size=350`), sin plantillas ni avisos de versión, menos conexiones al
   pooler y poda del historial.

3. **Sin usuario para entrar:** el usuario de prueba de local (`test@example.com`) no existe en producción
   ni debe existir: tiene una contraseña conocida. El administrador se crea al arrancar desde
   `ADMIN_EMAIL` y `ADMIN_PASSWORD`, y **el registro público está cerrado en producción**
   (`REGISTRATION_ENABLED`): con él abierto, cualquiera entraría al panel y podría aprobar correos que
   salen de tu Gmail. El panel no distingue roles: quien entra puede todo.

4. **Página en blanco tras desplegar:** Render termina el TLS y reenvía por `http`, así que Laravel
   generaba las URLs de los assets con `http` y el navegador las bloqueaba por contenido mixto. Arreglo:
   `trustProxies(at: '*')` en `bootstrap/app.php`.

## 3. Render: n8n

1. El mismo blueprint crea el servicio `reformas-n8n` con la imagen oficial.
2. Variables: las `DB_POSTGRESDB_*` de Supabase (esquema `n8n`), `N8N_ENCRYPTION_KEY`
   (`openssl rand -hex 32`), y `N8N_HOST` y `WEBHOOK_URL` con la URL que le dé Render.
3. Al abrirlo la primera vez, crear el usuario del editor.
4. **Rehacer la credencial de Gmail**: la URI de redirección pasa a
   `https://<n8n en Render>/rest/oauth2-credential/callback`; hay que añadirla en Google Cloud.
5. Crear la credencial de Telegram con el token del bot.
6. Importar el flujo (`n8n/workflows/reformas-avisos.json`), poner el `chat_id` real y **publicarlo**.

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

| Riesgo                    | Qué hacer                                                                       |
| ------------------------- | ------------------------------------------------------------------------------- |
| El editor de n8n expuesto | Contraseña fuerte en el usuario del editor                                      |
| Datos reales              | **Solo datos ficticios**: el tier gratuito de la IA puede usarlos para entrenar |
| La API pública            | Revisar `API_REQUESTS_PER_MINUTE`                                               |
| Pérdida de datos          | Supabase hace copias; exportar el flujo de n8n al repositorio tras cada cambio  |

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
