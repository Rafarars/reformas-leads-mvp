# Reformas Leads — reglas del proyecto

Portafolio público de Rafael Rodríguez Sosa: recepción y clasificación de leads de reformas
que **no pierde un lead aunque un servicio externo esté caído**. Todo lo que entre al
repositorio lo va a leer un reclutador técnico.

**Empezar aquí:** [`docs/RETOMAR.md`](docs/RETOMAR.md) (estado y checklist), [`docs/spec.md`](docs/spec.md) (qué se construye),
[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) (cómo se organiza) y
[`docs/BOOTSTRAP.md`](docs/BOOTSTRAP.md) (por qué el entorno es como es).

## Idioma

| Elemento                                                          | Idioma      |
| ----------------------------------------------------------------- | ----------- |
| Identificadores, archivos, clases, `data-test`, títulos de prueba | **Inglés**  |
| Comentarios en el código                                          | **Español** |
| Texto visible al usuario, documentación, mensajes de commit       | **Español** |

## Comentarios

**Pocos y cortos.** Explican **por qué**, nunca narran lo que el código ya dice.

## Commits

Los hace Claude, **incluido el push**, y **siempre explica qué commiteó**: archivos, qué
cambió y por qué. Mensajes en español, una línea, en infinitivo:

```
Agregar endpoint de salud que verifica la conexion a la base de datos
```

**Nunca firmas ni atribuciones**: ni `Co-Authored-By`, ni enlaces de sesión.

## Antes de commitear

```bash
make verify         # cambios de código o configuración
make verify-clean   # además, al tocar Dockerfile, compose, dependencias o compilación
```

Solo se salta en cambios de documentación pura. **Un arreglo verificado a medias no está
verificado**: si algo corre en local, en Docker y en el CI, se comprueba en los tres.

`verify-clean` no recrea la base: el caso de una base **vacía y sin migrar** solo lo cubre
el CI. Un cambio que dependa de tablas existentes debe probarse también contra una base
nueva.

## Arquitectura

Hexagonal con rebanado vertical, **conviviendo con la estructura de Laravel**. Convención
completa en [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md). Resumen:

- **Núcleo en `src/`** (namespace `Reformas\`): `src/<Contexto>/<Módulo>/{Domain,Application,Infrastructure}`
- **Controladores en `app/Http/Controllers`**, uno por acción (`LeadPostController`). Validan la
  entrada, llaman al caso de uso y traducen a HTTP. **Nada de lógica de negocio**
- **Modelos de Eloquent y repositorios dentro del contexto**, en
  `Infrastructure/Persistence/Eloquent`. `User` se queda en `app/Models` (starter kit)
- **`Domain` y `Application` no usan Laravel, Eloquent, HTTP ni Carbon.** Una prueba de
  arquitectura lo comprueba
- Casos de uso `final readonly class` con un único `__invoke()`. Las reglas de negocio salen a
  servicios de dominio: responsabilidad única se mide en **razones para cambiar**
- Carpetas de aplicación que dicen qué hace el sistema: `Receive/`, nunca `UseCases/`
- Errores de dominio con sufijo `Error` y por categoría; excepciones de infraestructura con
  sufijo `Exception`. La traducción a HTTP, en un solo lugar

## Pruebas

- **Cada guarda se ve saltar en los dos sentidos**: que bloquea cuando debe y que **no**
  bloquea cuando no debe. Una guarda que salta de más se acaba desactivando
- **Un healthcheck no consulta tablas**: en el CI la base nace vacía y se migra después
- Dominio y aplicación se prueban **sin base de datos**; si necesitan infraestructura, la
  hexagonal está mal hecha
- `Http::preventStrayRequests()` y `withoutVite()` en el `TestCase` base: ninguna prueba de
  backend depende de servicios reales ni del frontend compilado
- Selectores `data-test` (convención del starter kit), sembrados al escribir el componente.
  Page Object Model: ningún `.spec.ts` conoce un selector
- Las pruebas que apagan servicios van en el proyecto `resilience`, al final, y restauran
  el entorno pase lo que pase. **Reintentan** hasta ver el cambio: tras reiniciar un
  servicio hay segundos en que todavía no responde

## Docker

- Cada servicio en su contenedor, en la red del compose, por nombre (`app`, `db`, `n8n`)
- Puertos publicados solo en `127.0.0.1`. Versiones de imagen fijas, nunca `latest`
- **Antes de fiarse de una salida filtrada con `grep`, mirar el código de salida** y guardar
  la salida completa: el error suele estar en la última línea

## Configuración y secretos

- **Ninguna credencial en el repositorio.** `.env` nunca; `.env.example` sí
- En producción, la configuración inválida **impide arrancar** (`php artisan app:check-config`
  en el `CMD` de la imagen)
- **Solo datos ficticios** pasan por la IA: el tier gratuito puede usarlos para entrenar
- Si se filtra un secreto: **se rota**, no se esconde

## Código derivado

`vendor/`, `node_modules/`, `public/build/` y los tipos de Wayfinder
(`resources/js/{actions,routes,wayfinder}`) **no se versionan** y se regeneran donde nace la
necesidad (`types:check` regenera Wayfinder antes de `tsc`). `.npmrc` tiene
`ignore-scripts=true`, que **desactiva los scripts `pre`/`post` de npm**: los enganches se
encadenan dentro del propio script.

## Comandos

`make help` los lista. Lo esencial: `make setup` (primera vez), `make up`, `make verify`.

## Documentación

| Archivo                | Contenido                                                            |
| ---------------------- | -------------------------------------------------------------------- |
| `docs/spec.md`         | Problema, alcance, arquitectura, resiliencia y orden de construcción |
| `docs/ARCHITECTURE.md` | Convención hexagonal: estructura, nombres y reglas                   |
| `docs/BOOTSTRAP.md`    | Bitácora del arranque y de cada tropiezo                             |
| `docs/FUTURE.md`       | Mejoras diferidas, con su porqué                                     |
| `docs/RETOMAR.md`      | Estado actual y checklist de lo que falta                            |
| `README.md`            | Portada del portafolio                                               |

Las decisiones con su porqué se guardan además en Engram, proyecto `reformas-leads-mvp`.
**Toda idea de futuro va a `docs/FUTURE.md`**: nada se construye antes de necesitarlo, pero
nada se pierde por no escribirlo.
