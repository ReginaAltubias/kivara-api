#!/bin/bash
# ============================================================================
# Kivara Local Development Setup Script
# ============================================================================
# This script initializes the local development environment with all
# necessary containers and Laravel dependencies.
# ============================================================================

set -e

echo "🚀 Iniciando setup do Kivara..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ============================================================================
# Step 1: Validate Prerequisites
# ============================================================================
echo -e "${BLUE}[1/6]${NC} Validando pré-requisitos..."

if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker não encontrado. Instale o Docker Desktop.${NC}"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}❌ Docker Compose não encontrado.${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Docker e Docker Compose encontrados${NC}"

# ============================================================================
# Step 2: Create .env from template
# ============================================================================
echo -e "${BLUE}[2/6]${NC} Configurando variáveis de ambiente..."

if [ ! -f ".env" ]; then
    cp .env.example .env
    echo -e "${GREEN}✓ Arquivo .env criado a partir de .env.example${NC}"
else
    echo -e "${YELLOW}⚠ .env já existe, pulando...${NC}"
fi

# Generate APP_KEY if empty
if ! grep -q "APP_KEY=base64:" .env; then
    echo -e "${YELLOW}⚠ APP_KEY não configurada. Gerando...${NC}"
    APP_KEY=$(openssl rand -base64 32)
    sed -i.bak "s/APP_KEY=/APP_KEY=base64:${APP_KEY}/" .env
    rm -f .env.bak
fi

# ============================================================================
# Step 3: Build Docker Images
# ============================================================================
echo -e "${BLUE}[3/6]${NC} Construindo imagens Docker..."

docker-compose build --no-cache

echo -e "${GREEN}✓ Imagens construídas com sucesso${NC}"

# ============================================================================
# Step 4: Start Services
# ============================================================================
echo -e "${BLUE}[4/6]${NC} Iniciando serviços..."

docker-compose up -d

# Wait for services to be healthy
echo -e "${YELLOW}⏳ Aguardando serviços ficarem saudáveis...${NC}"
sleep 10

# Check service health
POSTGRES_HEALTH=$(docker-compose ps postgres | grep -c "healthy" || echo 0)
REDIS_HEALTH=$(docker-compose ps redis | grep -c "healthy" || echo 0)

if [ "$POSTGRES_HEALTH" -eq 0 ] || [ "$REDIS_HEALTH" -eq 0 ]; then
    echo -e "${YELLOW}⚠ Aguardando mais tempo para os serviços iniciarem...${NC}"
    sleep 10
fi

echo -e "${GREEN}✓ Serviços iniciados${NC}"

# ============================================================================
# Step 5: Install Laravel Dependencies
# ============================================================================
echo -e "${BLUE}[5/6]${NC} Instalando dependências do Laravel..."

# Install Composer dependencies
docker-compose exec -T web composer install

# Copy .env if not exists
docker-compose exec -T web cp .env.example .env 2>/dev/null || true

# Generate app key
docker-compose exec -T web php artisan key:generate --force

# Run migrations
docker-compose exec -T web php artisan migrate --force

# Seed database (optional)
# docker-compose exec -T web php artisan db:seed

echo -e "${GREEN}✓ Dependências instaladas e banco de dados migrado${NC}"

# ============================================================================
# Step 6: Display Access Information
# ============================================================================
echo -e "${BLUE}[6/6]${NC} Finalizando..."

echo ""
echo -e "${GREEN}✅ Configuração completa!${NC}"
echo ""
echo -e "${BLUE}=== Acesso aos Serviços ===${NC}"
echo -e "🌐 API Web:        ${GREEN}http://localhost:8000${NC}"
echo -e "📊 PostgreSQL:     ${GREEN}localhost:5432${NC}"
echo -e "🔗 PgBouncer:      ${GREEN}localhost:6432${NC}"
echo -e "🔴 Redis:          ${GREEN}localhost:6379${NC}"
echo ""
echo -e "${BLUE}=== Comandos Úteis ===${NC}"
echo "Logs da API:             docker-compose logs -f web"
echo "Logs do Worker:          docker-compose logs -f worker"
echo "Acessar shell da API:    docker-compose exec web bash"
echo "Executar artisan:        docker-compose exec web php artisan <command>"
echo "Parar containers:        docker-compose down"
echo ""
echo -e "${YELLOW}📝 Próximos passos:${NC}"
echo "1. Edite .env com suas credenciais (S3, Stripe, etc.)"
echo "2. Crie suas migrations: docker-compose exec web php artisan make:migration"
echo "3. Desenvolva com auto-reload: volumes estão mapeados"
echo ""
