$ErrorActionPreference = 'Stop'

$apiDir = $PSScriptRoot
Set-Location $apiDir

Write-Host "== VolleyApp API setup ==" -ForegroundColor Cyan

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw "No se encontró 'php' en el PATH. Instala PHP 8.2+ y vuelve a intentarlo."
}
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    throw "No se encontró 'composer' en el PATH. Instala Composer 2 y vuelve a intentarlo."
}

if (Test-Path (Join-Path $apiDir 'artisan')) {
    Write-Host "Laravel ya está instalado en api/. Nada que hacer." -ForegroundColor Yellow
    exit 0
}

$overlay = Join-Path $env:TEMP 'volleyapp-overlay'
$scaffold = Join-Path $env:TEMP 'volleyapp-laravel'

Write-Host "1/6 Guardando el código de VolleyApp..." -ForegroundColor Cyan
if (Test-Path $overlay) { Remove-Item $overlay -Recurse -Force }
New-Item -ItemType Directory -Path $overlay | Out-Null
foreach ($dir in @('app', 'database', 'routes', 'bootstrap', 'config')) {
    $source = Join-Path $apiDir $dir
    if (Test-Path $source) {
        Copy-Item $source (Join-Path $overlay $dir) -Recurse -Force
    }
}

Write-Host "2/6 Creando un Laravel 11 limpio (esto puede tardar)..." -ForegroundColor Cyan
if (Test-Path $scaffold) { Remove-Item $scaffold -Recurse -Force }
composer create-project laravel/laravel $scaffold "11.*" --prefer-dist --no-interaction

Write-Host "3/6 Fusionando framework + VolleyApp..." -ForegroundColor Cyan
Copy-Item (Join-Path $scaffold '*') $apiDir -Recurse -Force
Copy-Item (Join-Path $overlay '*') $apiDir -Recurse -Force

Write-Host "4/6 Instalando Sanctum..." -ForegroundColor Cyan
composer require laravel/sanctum --no-interaction

Write-Host "5/6 Configurando entorno..." -ForegroundColor Cyan
if (-not (Test-Path (Join-Path $apiDir '.env'))) {
    Copy-Item (Join-Path $apiDir '.env.example') (Join-Path $apiDir '.env')
    php artisan key:generate
}

Write-Host "6/6 Enlazando almacenamiento público..." -ForegroundColor Cyan
php artisan storage:link | Out-Null

Write-Host ""
Write-Host "¡Listo!" -ForegroundColor Green
Write-Host "Siguiente paso: edita api/.env con tus credenciales de MySQL/PostgreSQL y ejecuta:" -ForegroundColor Green
Write-Host "  php artisan migrate --seed"
Write-Host "  php artisan serve"
Write-Host ""
Write-Host "API:      http://localhost:8000/api"
Write-Host "Frontend: cd ..\frontend; npm run dev  (usa VITE_API_URL=http://localhost:8000/api)"
