# VolleyApp API — script de arranque (Windows)

Este script convierte la carpeta `api/` (que contiene el código propio de VolleyApp)
en una instalación completa de Laravel 11.

## Requisitos

- PHP 8.2 o superior (`php -v`) — probado con PHP 8.4.27
- Composer 2 (`composer -V`)
- MySQL 8+ o PostgreSQL

## Uso

```powershell
cd api
powershell -ExecutionPolicy Bypass -File .\setup.ps1
```

El script:

1. Comprueba PHP y Composer.
2. Crea un proyecto Laravel 11 limpio en una carpeta temporal.
3. Fusiona el framework con el código de VolleyApp (`app/`, `config/`, `database/`, `routes/`, `bootstrap/`).
4. Instala **Laravel Sanctum** (`composer require laravel/sanctum`).
5. Genera `.env` y la `APP_KEY` si no existen.
6. Crea el enlace simbólico de almacenamiento público (para las imágenes).

Después, configura la base de datos en `api/.env` y ejecuta:

```bash
php artisan migrate --seed
php artisan serve
```

> `setup.ps1` no ejecuta migraciones automáticamente para no tocar bases de datos
> existentes. Ejecútalas tú cuando tengas el `.env` configurado.

## Usuarios sembrados

| Nombre | Email | Contraseña | Rol |
|---|---|---|---|
| `bobson` | `j.a.vansleeuwen@gmail.com` | `Vq7!mN2xL9pR4tK8` (temporal) | **Administrador** |
| `Entrenador Demo` | `demo@volley.app` | `password123` | Entrenador |

`bobson` se promueve a administrador tanto por migración como por su seeder, así que
puede abrir el panel `/admin/ejercicios` para validar los ejercicios de la comunidad.

## Problemas frecuentes

### Composer: "The zip extension and unzip/7z commands are both missing"

Activa `extension=zip` en el `php.ini` que uses (`php --ini`). En Laragon está en
`C:\laragon\bin\php\php-8.x\php.ini` y el DLL en `ext\php_zip.dll`.

Alternativa puntual:

```powershell
php -d extension=zip C:\laragon\bin\composer\composer.phar require laravel/sanctum
```

### "Trait Laravel\Sanctum\HasApiTokens not found"

Falta Sanctum. Ejecuta:

```bash
composer require laravel/sanctum
```

La migración `create_personal_access_tokens_table` ya está incluida en
`database/migrations`, así que solo hace falta `php artisan migrate`.

### La API responde 419/CSRF o CORS

El proyecto usa tokens Bearer (no cookies). Comprueba `FRONTEND_URL` en `.env` si
necesitas restringir el origen; por defecto `config/cors.php` permite `*`.
