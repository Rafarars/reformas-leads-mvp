# Seguridad: hallazgos pendientes

Resultado de la primera auditoría de seguridad del proyecto (28-sep-2026, commit `491f133`). Aquí
solo se **registran**: ninguno está corregido todavía. Cada hallazgo se comprobó leyendo el código;
donde la auditoría se equivocó o exageró, se dice.

## Cómo se hizo

- **Escáneres deterministas** (Semgrep, Gitleaks, OSV-Scanner, Trivy), sin ejecutar el proyecto.
- **Auditoría con agentes** siguiendo el método de
  [`cloudflare/security-audit-skill`](https://github.com/cloudflare/security-audit-skill), perfil
  `quick`: reconocimiento, búsqueda por unidades de cobertura y un verificador independiente por
  hallazgo. Todo lo que ejecutó código corrió en un contenedor sin red y sin el `.env`.
- Una sola pasada `quick` **no cubre todo**: el propio método estima que una corrida encuentra
  alrededor de la mitad de lo que encuentran varias.

## Confirmados

### 1. Adjuntos por la API sin comprobar a quién pertenece el lead

- **Dónde:** `src/Leads/Attachment/Application/Upload/AttachmentUploader.php:46`, llamado desde
  `POST /api/v1/leads/{lead}/attachments`.
- **Qué falta:** solo se comprueba que el lead exista, no que lo haya creado el cliente de la API que
  sube el archivo. La regla es que un integrador solo adjunta a sus propios leads.
- **Por qué importa más de lo que parece:** el hallazgo 2 le entrega a otro integrador el `id` de un
  lead existente, así que no hace falta adivinar el UUID.
- **Arreglo:** pasar el origen del cliente autenticado (`api_client_source`) al caso de uso y
  rechazar el adjunto si no coincide con el origen del lead. Con su test.
- **Severidad:** media.

### 2. La respuesta de la deduplicación devuelve el `id` del lead que ya existía

- **Dónde:** `src/Leads/Lead/Application/Receive/LeadReceiverResponse.php:20`, respuesta `200` de
  `POST /api/v1/leads`.
- **Qué pasa:** si el teléfono o el email ya estaban registrados, la respuesta incluye el `id` de ese
  lead, aunque lo haya creado otro canal. Revela que el contacto ya existía y abre el hallazgo 1.
- **Lo que NO es un fallo:** que la deduplicación busque en todos los canales y complete los campos
  vacíos es **el diseño** ("misma persona por teléfono o email", `LeadRegistrar.php:11` y
  `Lead.php:74-76`). La auditoría propuso buscar solo dentro del mismo canal: **no aplicarlo**,
  rompería esa regla.
- **Arreglo:** no devolver el `id` del lead existente a un canal distinto del que lo creó (por
  ejemplo, responder solo `repeated: true`), o cerrar el hallazgo 1, que es lo que ese `id` permite.
- **Severidad:** baja por sí solo; sube encadenado con el 1.

### 3. La demo de resiliencia está disponible en producción

- **Dónde:** `routes/web.php:31-34` y `app/Http/Controllers/Resilience/*`.
- **Qué pasa:** en producción se pueden inyectar hasta 50 leads por clic
  (`ResilienceLeadsPostController.php:12`), que pasan por Gemini y generan avisos reales por n8n.
- **Matiz:** el único usuario del panel es el administrador, así que no cruza ninguna frontera
  entre usuarios. La auditoría le puso severidad media; es **baja**, de higiene operativa.
- **Arreglo:** registrar esas rutas solo fuera de producción, o protegerlas con una opción explícita.

## Pendientes de comprobar en el despliegue

- **PDF adjuntos servidos `inline` sin `Content-Security-Policy: sandbox`**
  (`app/Http/Controllers/Attachments/AttachmentGetController.php:16`). Depende de las cabeceras que
  ponga Render y del visor del navegador; hoy los archivos los sube el propio equipo.
- **Webhook de n8n sin autenticación.** Riesgo conocido (`docs/FUTURE.md`, "Header Auth"). Faltan las
  dos partes: el nodo Webhook de `n8n/workflows/reformas-avisos.json` y la cabecera desde Laravel
  (`src/Delivery`). El nodo de Gmail envía al email que llega en la petición.

## Revisado y sin problema

- Autenticación del panel: registro y restablecimiento cerrados en producción, límite de intentos,
  2FA, passkeys y CSRF.
- Clasificación con IA: a Gemini no llegan nombre, email ni teléfono, y el contenido del lead se
  trata como dato.
- Dependencias: sin vulnerabilidades conocidas (OSV, `npm audit` y `composer audit`).
- Historial de git: sin secretos (Gitleaks).
- CI y cadena de suministro: `dependabot` con espera de 5 días, acciones oficiales con permisos de
  solo lectura, `ignore-scripts=true` en `.npmrc`. Semgrep sugiere fijar las acciones por SHA y una
  edad mínima de publicación en npm: son mejoras, no fallos.
