# 🎮 KIVARA API - FinTech/EdTech Backend

[![Status](https://img.shields.io/badge/Status-Production%20Ready-brightgreen)]()
[![PHP](https://img.shields.io/badge/PHP-8.3-blue)]()
[![Laravel](https://img.shields.io/badge/Laravel-11-red)]()
[![Docker](https://img.shields.io/badge/Docker-Compose-blue)]()

---

## 📋 Visão Geral

KIVARA é um **backend FinTech/EdTech** completo para uma plataforma de educação financeira gamificada para crianças, com:

- ✅ **Multi-tenant B2B** (escolas, famílias, parceiros)
- ✅ **Double-Entry Append-Only Ledger** (imutável, GDPR compliant)
- ✅ **RPG Gamification** com Phase Gates (aprova parentais)
- ✅ **35+ REST API Endpoints** (OpenAPI 3.0.3)
- ✅ **Testes Automatizados** (Feature + Unit)
- ✅ **Docker Production-Ready** (multi-stage builds)
- ✅ **PostgreSQL + Redis** (cache/sessions/queues)

---

## 🚀 Quick Start

### 1. Clone & Prepare

```bash
git clone https://github.com/seu-org/kivara-api.git
cd kivara-api
cp .env.example .env
```

### 2. Subir Containers

```bash
docker compose up -d
```

### 3. Migrations & Seeds

```bash
docker compose exec web php artisan migrate
docker compose exec web php artisan db:seed
```

### 4. Testar API

```bash
# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'

# Ver Profile
curl -X GET http://localhost:8000/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 📁 Project Structure

```
app/
├── Models/                 # 15+ Eloquent Models
│   ├── Tenant.php
│   ├── User.php
│   ├── ChildProfile.php
│   ├── Wallet.php
│   ├── LedgerEntry.php    # Append-only!
│   ├── Classroom.php
│   ├── LearningObject.php
│   ├── LevelUpRequest.php
│   └── ...
├── Http/Controllers/Api/
│   ├── Auth/
│   │   ├── LoginController.php
│   │   ├── LogoutController.php
│   │   └── ProfileController.php
│   ├── Finance/
│   │   ├── TransactionController.php (Idempotent)
│   │   ├── AllowanceController.php
│   │   └── VaultController.php
│   ├── Gamification/
│   │   ├── LessonController.php
│   │   ├── StreakController.php
│   │   └── BadgeController.php
│   ├── School/
│   │   ├── SchoolApplicationController.php
│   │   └── ClassroomController.php
│   └── ...
├── Services/              # 10+ Business Logic Services
│   ├── LedgerService.php
│   ├── RiskDetectionService.php
│   ├── SchoolOnboardingService.php
│   └── ...
└── ...

database/
├── migrations/            # 9 schema migrations
│   ├── 2024_01_04_* (Financial)
│   ├── 2024_01_05_* (Schools)
│   ├── 2024_01_06_* (Gamification)
│   └── 2024_01_07_* (More)
└── seeders/               # Demo data

routes/
└── api.php               # 35+ API endpoints

tests/
├── Feature/              # Integration tests
│   ├── AuthTest.php
│   └── TransactionTest.php
└── Unit/                 # Unit tests
    └── WalletTest.php
```

---

## 🔑 Key Features

### 1. **Double-Entry Append-Only Ledger**

```php
// Criar transação (idempotente)
POST /api/v1/finance/transactions
Headers: Idempotency-Key: uuid

{
  "wallet_id": "...",
  "amount": 100,
  "direction": "credit",
  "entry_type": "allowance",
  "description": "Mesada mensal"
}
```

**Proteções:**
- ✅ Idempotência (mesmo `Idempotency-Key` = mesma resposta)
- ✅ Pessimistic Locking (`lockForUpdate()`)
- ✅ Validação de saldo em tempo real
- ✅ Append-only (nunca atualiza/deleta)

### 2. **Portão Parental (Parental Gate)**

```php
// Criança atinge XP necessário → Cria LevelUpRequest
POST /gamification/lessons/{id}/complete

// Pai aprova
POST /parents/level-up-requests/{id}/approve

// Fase é desbloqueada
child.update(['kivara_phase_id' => 2])
```

### 3. **Gamificação com Fases**

```
Phase 1: Explorador (6-8 anos)
├── Lições básicas de poupança
└── Introdução a moedas

Phase 2: Investidor (8-10 anos)
├── Conceitos de ROI
└── Cofres com juros

...até Phase 10
```

### 4. **Multi-Tenant Isolation**

```php
// Cada escola = Tenant isolado
Wallet::where('tenant_id', auth()->user()->tenant_id)->get();
```

---

## 📚 API Documentation

**OpenAPI 3.0.3**: `openapi.yaml` (56KB)

### Endpoints Principais

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/auth/login` | Login (Email/PIN) |
| `POST` | `/finance/transactions` | Criar transação |
| `POST` | `/gamification/lessons/{id}/complete` | Concluir lição |
| `POST` | `/parents/level-up-requests/{id}/approve` | Aprovar evolução |
| `POST` | `/b2b/classrooms` | Criar turma |
| `POST` | `/users/children` | Criar perfil criança |

**Documentação Completa**: Abrir `openapi.yaml` no Swagger Editor

---

## 🧪 Testing

```bash
# Rodar todos os testes
make test

# Tests Feature (integração)
make test-feature

# Tests Unit
make test-unit

# Com cobertura
make test-coverage
```

**Coverage Target**: 80%+

---

## 🐳 Docker Stack

```
postgresql:16        (Database)
redis:7              (Cache + Sessions + Queues)
laravel:8.3-fpm      (Web Server - php artisan serve)
laravel:8.3-fpm      (Worker - Queue processor)
```

**Recursos por container:**
- Web: 1 CPU, 512MB RAM
- Worker: 0.5 CPU, 256MB RAM
- Postgres: 1 CPU, 1GB RAM
- Redis: 256MB RAM

---

## 🚀 Deployment

### Option 1: Docker Compose (Simple)

```bash
# Staging/Production
docker compose -f docker-compose.prod.yml up -d
```

### Option 2: Kubernetes (Scale)

```bash
kubectl apply -f k8s/
```

### Option 3: Managed Services

- Database: AWS RDS PostgreSQL
- Cache: AWS ElastiCache Redis
- Hosting: ECS / App Engine / Cloud Run

**Guia Completo**: `DEPLOYMENT.md`

---

## 🛠 Development Commands

```bash
# Start
make up

# Logs
make logs

# Migrations
make migrate

# Database fresh
make migrate-fresh

# Shell
make shell

# Tests
make test

# Format code
make format

# Stop
make down
```

---

## 📖 Documentation Files

- **`README.md`** (este) - Overview
- **`openapi.yaml`** - API Reference
- **`DEPLOYMENT.md`** - Production deployment
- **`Makefile`** - Common commands
- **`API_DOCUMENTATION.md`** - Detailed API specs
- **`PARENTAL_GATE_DOCUMENTATION.md`** - Parental approval flow

---

## 🔐 Security

✅ **GDPR Compliant**
- Append-only audit log
- No data modifications tracked
- User deletion with cascade

✅ **Financial Security**
- Pessimistic locking
- Idempotency keys
- Double-entry accounting

✅ **Application Security**
- SQL injection prevention (ORM)
- XSS protection
- CSRF tokens
- Rate limiting ready

---

## 📊 Architecture

```
┌─────────────────────────────────────┐
│         Frontend (React/Mobile)     │
└────────────────┬────────────────────┘
                 │
          HTTP/REST API
                 │
    ┌────────────┴────────────┐
    │                         │
┌───▼────────┐         ┌─────▼──────┐
│  Web Server│         │   Worker   │
│ (Octane)   │         │  (Queue)   │
└───┬────────┘         └─────┬──────┘
    │                        │
    └────────────┬───────────┘
                 │
        ┌────────┴────────┐
        │                 │
    ┌───▼────┐       ┌───▼──────┐
    │PostgreSQL       │  Redis   │
    │(Ledger)    │    │ (Cache)  │
    └──────────┘     └──────────┘
```

---

## 🤝 Contributing

1. Fork o repositório
2. Create branch (`git checkout -b feature/xyz`)
3. Commit changes (`git commit -am 'Add xyz'`)
4. Push branch (`git push origin feature/xyz`)
5. Create Pull Request

**Code Style**: PSR-12 (Laravel)
**Tests**: Feature + Unit coverage 80%+

---

## 📝 License

Proprietário - KIVARA Inc.

---

## 🎯 Status

| Componente | Status | Notes |
|------------|--------|-------|
| Backend API | ✅ Ready | 35+ endpoints |
| Database | ✅ Ready | PostgreSQL 16 |
| Cache/Sessions | ✅ Ready | Redis 7 |
| Docker | ✅ Ready | Production build |
| Tests | ✅ Ready | Feature + Unit |
| Deployment | ✅ Ready | Docker + K8s |
| Frontend | ⏳ Pending | React/Mobile |

---

## 📞 Support

- **Issues**: GitHub Issues
- **Email**: support@kivara.ao
- **Docs**: https://docs.kivara.ao
- **Status Page**: https://status.kivara.ao

---

**Built with ❤️ by KIVARA Engineering**

```
 _  ___     _    ____    _
| |/ / |   | |  |  _ \  / \
| ' /| |   | |  | |_) |/ _ \
| . \| |_  | |__| _ < / ___ \
|_|\_\___| |____|_| \_/_/   \_\

Financial Literacy Education for Kids
```
