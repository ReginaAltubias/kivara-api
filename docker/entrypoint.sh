#!/bin/sh

set -e

echo "🚀 KIVARA API Starting..."

# ============================================================================
# Aguardar PostgreSQL estar pronto
# ============================================================================
echo "⏳ Aguardando PostgreSQL..."
while ! nc -z "$DB_HOST" "$DB_PORT"; do
  sleep 1
done
echo "✅ PostgreSQL pronto"

# ============================================================================
# Gerar APP_KEY se não existir
# ============================================================================
if [ -z "$APP_KEY" ]; then
  echo "🔑 Gerando APP_KEY..."
  php artisan key:generate --force
fi

# ============================================================================
# Executar Migrations
# ============================================================================
if [ "$APP_ENV" = "local" ] || [ "$APP_ENV" = "testing" ]; then
  echo "📦 Executando Migrations..."
  php artisan migrate --force
  echo "✅ Migrations completas"
fi

# ============================================================================
# Seed Data (opcional)
# ============================================================================
if [ "$SEED_DATABASE" = "true" ]; then
  echo "🌱 Executando Seeds..."
  php artisan db:seed
  echo "✅ Seeds completas"
fi

# ============================================================================
# Laravel Artisan Serve (Fallback - sem Octane)
# ============================================================================
echo "🎯 Iniciando Laravel Server..."
php artisan serve --host=0.0.0.0 --port=8000
