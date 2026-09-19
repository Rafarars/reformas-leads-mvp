# Cómo se usa

Qué hace cada pantalla, qué pasa detrás y por qué. Para configurar n8n está `docs/N8N.md`; para
desplegar, `docs/DEPLOY.md`.

## El recorrido completo

```
1. Entra un lead            Recepción (panel) · API v1 · n8n u otro integrador
        │                   ↳ se guarda al instante y se avisa al CRM
        ▼
2. La IA lo clasifica       en segundo plano, ~2-4 s
        │                   ↳ categoría, urgencia, qué falta, qué no cuadra y un borrador
        ▼
3. Aviso al EQUIPO          Telegram, con urgencia, resumen y a quién llamar
        │
        ▼
4. Una persona revisa       Clasificación: confirma o corrige, adjunta fotos, edita el borrador
        │
        ▼
5. Aprueba el mensaje       y solo entonces sale el correo al CLIENTE (Gmail)
```

**Regla que lo ordena todo:** nada que toque al cliente sale sin que una persona lo apruebe.

## Qué canal se usa y cuándo

| Cuándo                               | Evento                           | Canal        | Para quién             |
| ------------------------------------ | -------------------------------- | ------------ | ---------------------- |
| Llega el lead                        | `lead.received`                  | CRM          | El sistema comercial   |
| La IA termina (o lo deja a revisión) | `lead.classified`                | **Telegram** | El equipo, para actuar |
| Una persona aprueba el borrador      | `lead.customer_message_approved` | **Gmail**    | El cliente             |

Dónde se configura: en Laravel, `config/services.php` → `delivery.destinations`; en n8n, el nodo
**Switch** del flujo, que mira `body.event`. Cada destino tiene su propia cola de reintentos y su
circuit breaker: si Gmail falla, Telegram sigue.

## Recepción

Registra a una persona interesada. Si el teléfono o el email ya existen, **no crea un lead nuevo**:
lo anota como contacto repetido, completa lo que faltaba y no pisa lo que ya había.

No se suben fotos aquí a propósito: el lead aún no existe y una subida lenta o fallida no puede
hacer que se pierda.

## Clasificación

Es la bandeja de trabajo. Por cada lead:

| Bloque                     | Qué hacer                                                                                                          |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| Lo que escribió la persona | Contexto: qué marcó, zona, presupuesto y descripción                                                               |
| Propuesta de la IA         | Categoría, urgencia, confianza y el motivo. **Falta para presupuestar** y **datos que no cuadran**                 |
| Confirmar o corregir       | Dos desplegables y el botón **Confirmar**. La decisión humana manda                                                |
| **Fotos y planos**         | Arrastra el archivo a la zona punteada o pulsa para elegirlo; antes marca si es **foto** o **plano**. Se sube solo |
| Borrador del mensaje       | Se puede editar. **Aprobar mensaje** dispara el correo al cliente                                                  |

- Los archivos subidos aparecen como etiquetas con su nombre y peso; al pulsarlas se abren. **La app
  comprueba la sesión antes de servirlos**: no son URLs públicas.
- Admite JPG, PNG, WEBP y PDF, hasta 10 MB.
- **Las fotos no se adjuntan al correo**: van del cliente hacia dentro, para poder presupuestar.
- Un lead sin clasificar aparece como **Pendiente de revisión** con el motivo (la IA no respondió, no
  estaba configurada o su respuesta no era válida).

## Resiliencia

La pantalla de demostración. Permite **tumbar el CRM simulado**, inyectar leads de prueba y ver la
cola crecer con su próximo reintento, el estado de cada aviso y **un circuito por destino**. Al
restablecer el CRM, la cola se drena sola y sin duplicados.

## Cuánto tarda cada cosa

| Paso                 | Tiempo normal         | Por qué puede tardar más                                              |
| -------------------- | --------------------- | --------------------------------------------------------------------- |
| Guardar el lead      | Inmediato             | —                                                                     |
| Clasificación con IA | 2-4 s                 | Hay un límite de 12 llamadas por minuto: con varios leads, hacen cola |
| Aviso por Telegram   | < 1 s tras clasificar | Si n8n está dormido o caído, se reintenta con esperas crecientes      |
| Correo al cliente    | < 1 s tras aprobar    | Igual: el outbox reintenta hasta que el destino responde              |

Recibir un lead **nunca espera a la IA**: si el modelo se cae, el lead entra igual y queda para
revisión humana.

## Cosas que confunden la primera vez

| Situación                                                  | Qué pasa en realidad                                                                                              |
| ---------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Apruebo el borrador y no llega el correo                   | Los leads de prueba usan correos `@example.com`, que **rebotan**. Usa un correo real o un alias `+algo@gmail.com` |
| El correo sale de mi Gmail                                 | n8n envía con la cuenta conectada: en una empresa sería el buzón del negocio                                      |
| Llegan Telegrams de "Lucía Fernández" sin haber hecho nada | Son los leads que crean las pruebas end-to-end al ejecutarse                                                      |
| Subí una foto y no la veo                                  | Aparece como etiqueta encima de la zona de carga, junto al contador `Fotos y planos (n)`                          |
| El aviso al equipo no trae el borrador                     | A propósito: ese aviso es para decidir y actuar, no para leer un correo sin aprobar                               |
