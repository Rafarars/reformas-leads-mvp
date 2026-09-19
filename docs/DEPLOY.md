# Despliegue

Objetivo: el proyecto en internet **con coste cero**, sin que se duerma nada, para poder enseñar la
demo de resiliencia desde un enlace.

## Por qué un servidor propio y no una plataforma gestionada

El esquema inicial (base en Supabase, app y n8n en Render) dejó de encajar:

| Pieza                   | Plan gratuito hoy                         | Consecuencia                                                   |
| ----------------------- | ----------------------------------------- | -------------------------------------------------------------- |
| Base en Supabase        | 500 MB, se **pausa a los 7 días** sin uso | Hay que mantenerla viva con un ping                            |
| App en Render           | Se **duerme a los 15 min**                | El primer visitante espera ~1 minuto                           |
| **Cola y planificador** | **Sin plan gratuito** (7 $/mes)           | Sin cola no hay entregas, ni IA, ni outbox: **se cae la demo** |
| n8n en Render           | Se duerme                                 | El primer aviso falla y entra en reintentos                    |

La cola es el corazón del proyecto, así que partirlo en servicios gestionados sale **más caro y
peor** que una máquina donde corre el `docker-compose` entero.

**Elegido:** una VM **Oracle Cloud Always Free** (ARM, 2 vCPU y 12 GB), gratis de forma permanente.
Alternativa sin tarjeta, con recortes, en la última sección.

---

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
