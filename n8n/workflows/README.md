# Flujos de n8n

`reformas-avisos.json` es el flujo que reparte los avisos del outbox:

```
POST /webhook/reformas-events ─► Switch (body.event) ─┬─ lead.classified ────────────────► Telegram
                                                      ├─ lead.customer_message_approved ─► Gmail
                                                      └─ cualquier otro ─────────────────► Stop and Error
```

## Qué guarda y qué no

Guarda los nodos, sus expresiones y **el nombre** de las credenciales. **No guarda ningún secreto**: el
token del bot y el OAuth de Gmail viven cifrados dentro de n8n. El `chat_id` de Telegram está sustituido
por `REEMPLAZAR_POR_TU_TELEGRAM_CHAT_ID`.

## Importar en una instalación limpia

```bash
docker cp n8n/workflows/reformas-avisos.json reformas-n8n:/tmp/avisos.json
docker exec reformas-n8n n8n import:workflow --input=/tmp/avisos.json
```

Después, dentro de n8n:

1. Crear las credenciales **Gmail OAuth2** y **Telegram** (ver `docs/N8N.md`) y seleccionarlas en sus nodos.
2. Poner el `chat_id` real en el nodo de Telegram.
3. **Publicar** el flujo: la URL `/webhook/...` solo responde con el flujo publicado.

## Volver a exportar tras un cambio

```bash
docker exec reformas-n8n n8n export:workflow --id=<ID> --output=/tmp/avisos.json
docker cp reformas-n8n:/tmp/avisos.json ./n8n/workflows/reformas-avisos.json
```

Revisar el resultado antes de commitear: no debe contener tokens ni el `chat_id`.
