# Flujos de n8n

`reformas-avisos.json` reparte los avisos del outbox. Laravel manda **un aviso por persona conectada**,
con su `chat_id` dentro del cuerpo:

```
POST /webhook/reformas-events ─► Switch (body.event) ─┬─ lead.classified ───────────────► Telegram ─┬─ bien ──► 200
                                                      │                                            └─ error ─► ¿bot bloqueado? ─ sí ─► 422
                                                      │                                                                     └ no ─► 500
                                                      ├─ lead.customer_message_approved ─► Gmail ─────► 200
                                                      └─ cualquier otro ─────────────────► Stop and Error (500)
```

El **422** es lo que Laravel entiende como fallo permanente: aparta ese aviso sin contarlo para el
circuito, así una persona que bloqueó el bot no deja sin avisos al resto. Cualquier otro error devuelve
500 y se reintenta (ver `docs/ARCHITECTURE.md`, "Outbox y entrega").

`reformas-vincular-telegram.json` atiende a quien escribe al bot:

```
Telegram Trigger ─► ¿/start con enlace? ─┬─ sí ─► POST /api/v1/telegram/link ─► ¿200? ─┬─ sí ─► "Listo"
                                         │                                              └─ no ─► el motivo que da la app
                                         └─ no ─► "Entra en Ajustes → Telegram y pulsa Conectar"
```

## Qué guardan y qué no

Guardan los nodos, sus expresiones y **el nombre** de las credenciales. **Ningún secreto**: el token del
bot, el OAuth de Gmail y la clave del bot (`BOT_API_KEY`) viven cifrados dentro de n8n. Tampoco llevan
`chat_id`, porque ya no hay ninguno fijo.

Al exportar se quitan además `id`, `versionId`, `instanceId`, los identificadores internos de las
credenciales y los `webhookId`. El del `Telegram Trigger` es parte de la URL a la que Telegram entrega
los mensajes: publicado, cualquiera podría enviarle actualizaciones falsas. Al importar, n8n los genera
de nuevo.

## Importar en una instalación limpia

```bash
docker cp n8n/workflows/reformas-avisos.json reformas-n8n:/tmp/avisos.json
docker exec reformas-n8n n8n import:workflow --input=/tmp/avisos.json
docker cp n8n/workflows/reformas-vincular-telegram.json reformas-n8n:/tmp/vincular.json
docker exec reformas-n8n n8n import:workflow --input=/tmp/vincular.json
```

Después, dentro de n8n:

1. Crear las credenciales **Gmail OAuth2**, **Telegram** y **Header Auth** "Reformas · API bot"
   (`Authorization: Bearer <BOT_API_KEY>`), y seleccionarlas en sus nodos. Ver `docs/N8N.md`.
2. **Publicar** los dos flujos. El `Telegram Trigger` necesita que n8n tenga `WEBHOOK_URL` con una URL
   **HTTPS** pública: al publicar, le dice a Telegram adónde mandar los mensajes.

## Volver a exportar tras un cambio

Desde el editor, **Download**, y limpiar los metadatos anteriores antes de commitear. Revisar que no
contenga tokens, claves ni `chat_id`.
