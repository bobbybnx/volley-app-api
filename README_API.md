# VolleyApp API (Laravel 11 + Sanctum)

API REST para el catálogo global de ejercicios con flujo de validación, las sesiones de
tres bloques, los perfiles de entrenador (con avatar/banner) y la red social de
VolleyApp.

## Instalación

Desde `api/`:

```powershell
powershell -ExecutionPolicy Bypass -File .\setup.ps1
```

o en macOS/Linux:

```bash
bash setup.sh
```

Después configura `.env` y ejecuta:

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Los seeders crean el catálogo, una sesión demo, tres perfiles sociales de ejemplo
(dos públicos y uno privado) y el usuario `bobson`, que es **administrador**.

## Autenticación y perfil

Todas las rutas salvo registro/login usan `Authorization: Bearer <token>`.

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/auth/register` | Body: `name`, `username?`, `display_name?`, `email`, `password`, `password_confirmation` → `{ user, token }` |
| POST | `/api/auth/login` | Body: `email`, `password` → `{ user, token }` |
| POST | `/api/auth/logout` | Invalida el token actual |
| GET | `/api/auth/user` | Usuario autenticado con `initials`, `is_admin`, `profile_image_url`, `banner_image_url` y conteos (`exercises_count`, `workouts_count`, `public_workouts_count`) |
| PUT | `/api/profile` | `name`, `display_name`, `username`, `bio`, `is_private`, `theme` (`light`/`dark`), `email` |
| PUT | `/api/profile/password` | `current_password`, `password`, `password_confirmation` |
| POST | `/api/profile/avatar` | `multipart/form-data` con `image` (≤ 5 MB). Reemplaza el avatar anterior |
| DELETE | `/api/profile/avatar` | Elimina el avatar y su archivo |
| POST | `/api/profile/banner` | `multipart/form-data` con `image` (≤ 8 MB). Reemplaza el banner anterior |
| DELETE | `/api/profile/banner` | Elimina el banner y su archivo |

`username` es único, de 3–50 caracteres (`alpha_dash`). Si no se envía al registrarse,
se genera uno único a partir del nombre o del correo.

Las respuestas de las rutas de imagen devuelven `{ message, user }`, donde `user` incluye
`profile_image_url` y `banner_image_url` (URLs públicas resueltas con `Storage::url()`).
Las imágenes se guardan en `users/avatars` y `users/banners` del disco `public`.

## Catálogos

| Método | Ruta |
|---|---|
| GET | `/api/categories` |
| GET | `/api/body-areas` |
| GET | `/api/methodologies` |
| GET | `/api/fundamentals` |

`categories.type` vale `warmup` (Calentamiento) o `physical` (Fuerza, Velocidad,
Resistencia, Flexibilidad).

## Ejercicios (catálogo global con validación)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/exercises` | Paginado (20, `per_page` hasta 100). Filtros: `type`, `section`, `category_id`, `methodology_id`, `body_area`, `fundamental`, `q`, `mine=1`. Devuelve los validados + los pendientes del propio usuario |
| POST | `/api/exercises` | `multipart/form-data`. Nace pendiente (`is_validated = false`) salvo que lo cree un admin |
| GET | `/api/exercises/{id}` | Incluye relaciones, `image_url`, `section`, `is_validated`, `can_edit` y `can_delete` |
| PUT/PATCH | `/api/exercises/{id}` | Solo el autor. Con archivos usar POST + `_method=PUT` |
| DELETE | `/api/exercises/{id}` | Autor **o administrador**. Soft delete + borra imagen |
| POST | `/api/exercises/{id}/upload-image` | Solo el autor. Campo `image` (≤ 5 MB) |

Visibilidad:

- Los ejercicios **validados** (`is_validated = true`) son globales: cualquier entrenador
  autenticado los ve y puede reutilizarlos en sus sesiones.
- Los **pendientes** solo los ve su autor (y un administrador en el panel). Un tercero
  recibe `403` al abrir la ficha.
- `can_edit` es `true` solo para el autor; `can_delete` es `true` para el autor o un
  administrador.

La respuesta incluye `image_url` (URL pública resuelta con `Storage::url()`) y `section`
(`warmup`, `physical` o `main`).

### Crear calentamiento

```
title=Movilidad articular
type=warmup
description=...
category_id=1          # categoría con type=warmup
measurement_mode=time
default_time_seconds=240
default_series=1
body_areas[]=2
```

### Crear ejercicio físico

```
title=Sentadilla con salto
type=physical
description=...
category_id=2          # Fuerza
measurement_mode=repetitions
default_reps=10
default_series=4
body_areas[]=2
body_areas[]=3
image=<archivo>
```

### Crear ejercicio técnico-táctico

```
title=Recepción en W
type=technical_tactical
description=...
methodology_id=3
fundamentals[]=3
fundamentals[]=5
```

## Sesiones de entrenamiento

Cada sesión tiene **tres bloques obligatorios**:

| Bloque (`section`) | Tipo de ejercicio admitido |
|---|---|
| `warmup` | `warmup` |
| `physical` | `physical` |
| `main` | `technical_tactical` |

Si falta un bloque o un ejercicio no corresponde a su bloque, la API responde `422`.

Un ejercicio **pendiente** solo puede usarse en sesiones de su propio autor. Si otro
entrenador intenta añadirlo, la API responde `422` indicando que aún no está validado.
En las sesiones públicas, los ejercicios pendientes se ocultan a los terceros (siguen
visibles para el autor y para un administrador).

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/workouts` | Sesiones del usuario, con `exercises`, `sections_summary` y `exercises_count` |
| POST | `/api/workouts` | Crea la sesión y sus tres bloques |
| GET | `/api/workouts/{id}` | Detalle si es visible para el usuario |
| PUT/PATCH | `/api/workouts/{id}` | Datos generales; si incluye `exercises`, reemplaza los bloques |
| DELETE | `/api/workouts/{id}` | Solo el autor. Soft delete |
| PUT | `/api/workouts/{id}/exercises` | Sincroniza los tres bloques de golpe |
| POST | `/api/workouts/{id}/add-exercise` | Añade un ejercicio a un bloque |
| PUT | `/api/workouts/{id}/exercises/{exerciseId}` | Edita cargas (`series`, `reps`, `time_seconds`, `rest_seconds`, `notes`) |
| PUT | `/api/workouts/{id}/exercises/{exerciseId}/order` | Body `{ "order": 2, "section": "physical" }` |
| DELETE | `/api/workouts/{id}/exercises/{exerciseId}` | Quita de la sesión |
| PUT | `/api/workouts/{id}/feedback` | Feedback global: `rating` (1–5), `feedback_notes` |
| PUT | `/api/workouts/{id}/exercises/{exerciseId}/feedback` | Feedback por ejercicio: `rating`, `feedback_notes` |

### Crear sesión con tres bloques

```json
{
  "title": "Sesión de recepción",
  "date": "2026-10-24",
  "status": "planned",
  "description": "Primer toque bajo presión",
  "notes": "6 balones",
  "rating": 4,
  "feedback_notes": "Buena intensidad",
  "is_public": true,
  "exercises": [
    { "exercise_id": 1, "section": "warmup", "order": 0, "series": 1, "time_seconds": 240 },
    { "exercise_id": 2, "section": "physical", "order": 0, "series": 4, "reps": 10, "rest_seconds": 90 },
    { "exercise_id": 3, "section": "main", "order": 0, "series": 4, "time_seconds": 300, "rest_seconds": 60 }
  ]
}
```

## Red de entrenadores

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/trainers` | Directorio de perfiles públicos. Filtro `q` por nombre o `username`. Nunca devuelve `email` |
| GET | `/api/trainers/{username}` | Perfil + sesiones visibles. Devuelve `trainer`, `can_see_workouts` y `workouts` (o `null` si es privado y no eres el propietario) |
| GET | `/api/feed` | Muro de sesiones públicas de perfiles públicos |

Los perfiles incluyen `profile_image_url` y `banner_image_url`. Los ejercicios pendientes
no cuentan en los contadores públicos ni aparecen en el feed/perfil ajeno.

Reglas de privacidad:

- Perfil con `is_private = true`: sus sesiones no aparecen en el muro ni en el
  directorio; solo su propietario puede verlas.
- Sesión con `is_public = false`: solo la ve su autor.
- El acceso directo a una sesión no visible devuelve `403`.
- El correo solo se devuelve al propio usuario autenticado.

## Administración y validación

Requieren `users.is_admin = true`; el middleware `admin` responde `403` en caso
contrario.

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/admin/summary` | `{ pending, validated, total }` |
| GET | `/api/admin/exercises` | Listado de revisión. Filtros: `status` (`pending`/`validated`/`all`), `q`, `per_page` |
| PATCH | `/api/admin/exercises/{id}/validation` | Body `{ "is_validated": true\|false }` → `{ message, exercise }` |

- Al **aprobar**, el ejercicio pasa a ser global y aparece en el catálogo y el selector
  de sesiones de todos los entrenadores.
- Al **retirar la validación**, vuelve a ser visible solo para su autor.
- El administrador puede además eliminar cualquier ejercicio con
  `DELETE /api/exercises/{id}`.
- Los ejercicios creados por un administrador se guardan ya validados.

## Respuestas y errores

- Validación: `422` con `{ message, errors }`.
- No autenticado: `401`.
- Sin permisos de propietario o sin rol admin: `403`.
- Recurso inexistente: `404`.
- Listados: paginador Laravel (`data`, `total`, `current_page`, …).

## Autorización

- Ejercicios: lectura de validados para todos y de pendientes solo para su autor;
  edición solo del autor; borrado del autor o de un administrador.
- Sesiones: lectura según visibilidad; edición/borrado solo del autor.
- Feedback: solo el autor de la sesión puede valorar la sesión y sus ejercicios.
- Catálogos: solo lectura para usuarios autenticados.
- Administración: solo usuarios con `is_admin = true`.
