#!/usr/bin/env bash
set -euo pipefail

API_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$API_DIR"

echo "== VolleyApp API setup =="

command -v php >/dev/null 2>&1 || { echo "Falta PHP 8.2+ en el PATH."; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "Falta Composer 2 en el PATH."; exit 1; }

if [ -f "$API_DIR/artisan" ]; then
  echo "Laravel ya está instalado en api/. Nada que hacer."
  exit 0
fi

OVERLAY="${TMPDIR:-/tmp}/volleyapp-overlay"
SCAFFOLD="${TMPDIR:-/tmp}/volleyapp-laravel"

echo "1/6 Guardando el código de VolleyApp..."
rm -rf "$OVERLAY" "$SCAFFOLD"
mkdir -p "$OVERLAY"
for dir in app database routes bootstrap config; do
  [ -d "$API_DIR/$dir" ] && cp -R "$API_DIR/$dir" "$OVERLAY/$dir"
done

echo "2/6 Creando un Laravel 11 limpio (esto puede tardar)..."
composer create-project laravel/laravel "$SCAFFOLD" "11.*" --prefer-dist --no-interaction

echo "3/6 Fusionando framework + VolleyApp..."
cp -R "$SCAFFOLD"/. "$API_DIR"/
cp -R "$OVERLAY"/. "$API_DIR"/

echo "4/6 Instalando Sanctum..."
composer require laravel/sanctum --no-interaction

echo "5/6 Configurando entorno..."
if [ ! -f "$API_DIR/.env" ]; then
  cp "$API_DIR/.env.example" "$API_DIR/.env"
  php artisan key:generate
fi

echo "6/6 Enlazando almacenamiento público..."
php artisan storage:link >/dev/null

echo ""
echo "¡Listo! Edita api/.env y ejecuta:"
echo "  php artisan migrate --seed"
echo "  php artisan serve"
