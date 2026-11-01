# SRS v2 — KIVARA: Frontend + Backend (admin, encarregado, parceiro, escola, professor)

| | |
|---|---|
| **Versão** | 2.0 (rascunho para revisão) — substitui e alarga `SRS_API_KIVARA_v1.md` |
| **Data** | 2026-10-29 |
| **Repositórios** | Front: `appkiva-main` (React + Vite + Capacitor) · Back: `kivara-api` (Laravel 11, Sanctum, Spatie Permission, MySQL) |
| **API** | `https://kivara.ssgmea-mosap3.a2hosted.com/api/v1` |
| **Fora de âmbito (por agora)** | Perfis **adolescente (teen/yang)**, **jovem** e **criança**: login por PIN, `/youth`, cofres/diário/comunidade/IA, gamificação do aluno. Ficam documentados só onde tocam nas personas em âmbito. |

> **Como este documento foi feito:** (1) leitura de `routes/api.php`, controladores e migrações do `kivara-api`; (2) `php artisan route:list` (167 rotas controlador@método); (3) varredura automática das chamadas `api.*` do front (320 chamadas distintas) cruzadas com as rotas reais; (4) testes reais à API com as contas `admin` e `school`. (5) testes reais com **encarregado, parceiro e professor** (secção 2.1) e varrimento das páginas desses perfis no browser, a registar os pedidos que falham.

---

## 1. Objetivo e âmbito

Pôr o front a consumir a API real para as 5 personas em âmbito e, a seguir, **completar o back** nos pontos em que o front precisa de endpoints que ainda não existem ou estão incorretos. Cada requisito tem identificador (`FE-` front, `BE-` back, `X-` transversal), prioridade e critério de aceitação.

| Prioridade | Significado |
|---|---|
| **P0** | Bloqueia fluxo principal ou é risco de segurança |
| **P1** | Funcionalidade incompleta / erro visível ao utilizador |
| **P2** | Qualidade, consistência, dívida técnica |

### 1.1 Personas em âmbito

| Persona | Prefixo front | Role na API (`user.role`) | Role Spatie (middleware `role:`) | Token ability |
|---|---|---|---|---|
| Super Admin | `/admin/*` | `admin` | `admin` | `role:admin` |
| Escola (direção) | `/school/*` | `school` (conta demo) | **`coordinator` e `coordenador`** (as duas grafias coexistem) | — |
| Professor | `/teacher/*` | `teacher` | `teacher` | — |
| Encarregado | `/parent/*` | `parent` | — (rotas só com `auth:sanctum`) | `role:parent` |
| Parceiro | `/partner/*` | `partner` | `partner` | — |

---

## 2. Estado atual — resumo medido

| Indicador | Valor |
|---|---|
| Rotas API no back (`api/v1`) | 167 pares controlador@método |
| Chamadas distintas do front à API | 320 |
| Chamadas com rota correspondente no back | 95 (30 %) |
| Chamadas **sem** rota no back | 225 (inclui ~110 de áreas fora de âmbito: jovem, criança, comunidade, IA, diário, cofres do jovem…) |
| Rotas declaradas no back **sem método implementado** | 4 (secção 4.1) |
| Endpoints públicos que são *mock* | 1 (`GET /tenants`) |
| Contas que não conseguem sequer registar-se | todas (não existe `POST /auth/register`) |

Já adaptado no front nesta fase (testado): login, `/auth/me`, logout, troca de palavra-passe, planos de subscrição (admin), turmas (escola), perfil da escola (`/tenants/{id}`), tipos de plano, cabeçalhos/sidebar e modo escuro.

### 2.1 Resultados dos testes reais por perfil (2026-10-29)

Método: login real com cada conta demo, `GET` de todas as rotas da persona, `POST` com corpo vazio (validação), fluxo de criação para o encarregado, e varrimento de todas as páginas do perfil no browser registando as chamadas que falham.

**Encarregado (`parent@kivara.ao`)**

| Resultado | Detalhe |
|---|---|
| Login e rota inicial `/parent` | ✅ (a resposta de login não traz `must_change_password` nem `tenant_id`) |
| 15 `GET` do back | ✅ 200 (listas vazias): `/children`, `/tasks/household`, `/parent/tasks`, `/finance/allowances`, `/finance/wallet/balance|history`, `/vaults`, `/rewards`, `/missions`, `/parents/level-up-requests`, `/subscription/tiers|invoices`, `/onboarding/progress`, `/gamification/badges`, `/finance/transactions` |
| `GET /gamification/streaks/me` | ❌ **500** — `StreakController::show` usa `user()->id` como `child_profile_id` (violação de chave estrangeira para qualquer utilizador que não seja criança). |
| Fluxo ponta a ponta | ✅ criar filho (`POST /children {display_name, auth_method, pin}`) → tarefa (`{child_id,title,reward_amount}`) → mesada (`{frequency,child_id,amount}`) → cofre (`{name,target_amount,child_id}`) → recompensa (`{title,cost,child_id}`): todos `201` e a lista de tarefas reflete-os. |
| Dados fictícios no back | `GET /children/{id}/summary` devolve valores fixos (`lessons_completed:12`, `streak_days:7`, `kiva_points:0`). `GET /finance/wallet/balance` devolve `balance:100000` e `ledger_balance:0` (inconsistentes). |
| Validação (422 sem corpo) | ✅ campos obrigatórios coerentes com o front; mensagens cruas (`validation.required`). |
| **Front: pedidos que falham (17 páginas)** | `GET /wallets`, `/stats/emission`, `/admin/regional-prices`, `/admin/exchange-rates`, `/wallets/budget-exceptions`, `/subscription`, `/savings-vaults`, `/allowances`, `/dream-vaults` (+`/comments`), `/notifications`, `/schools`, `/parent/consent`, `/streaks` → **404**; **`GET /tasks` → 405** (a API só tem `/tasks/household`). |

**Parceiro (`partner@kivara.ao`)**

| Resultado | Detalhe |
|---|---|
| Login e rota inicial `/partner` | ✅ |
| `GET` OK | `/partner/dashboard`, `/partner/programs`, `/partner/challenges`, `/partner/reports/metrics`, `/partner/reports/chart-data`, `/onboarding/progress` |
| `GET /partner/wallet` | ❌ **500** — `PartnerWalletController` faz `Wallet::firstOrCreate` sem `tenant_id` e o utilizador parceiro **não tem tenant** (`Field 'tenant_id' doesn't have a default value`). |
| `GET /partner/limits`, `/partner-programs` | ❌ 404 (não existem) |
| Validação | ✅ programa `{title, allocated_budget}`; desafio `{program_id,title,goal_type,goal_target,reward_pool}`; top-up `{amount_aoa,payment_method}` |
| Front | Os programas aparecem com IDs de demonstração (`prog-001…003`): a página não usa os dados reais; pede `/partner/programs/{id}/invitations` e `/partner/limits` (404) e `/admin/currencies` (404). Páginas sem falhas: dashboard, recompensas, relatórios. |

**Professor (`teacher@kivara.ao`)**

| Resultado | Detalhe |
|---|---|
| Login e rota inicial `/teacher` | ✅ |
| `GET` OK | `/b2b/classrooms` (1 turma), `/b2b/challenges`, `/school/dashboard`, `/school/students`, `/school/challenges`, `/school/rewards`, `/onboarding/progress` |
| `GET /b2b/teacher/classrooms` | ❌ **500** — `Call to undefined method Classroom::pivot()`: `TeacherClassroomController` linha ~45 faz `withCount('pivot as student_count')` com uma relação que não existe. **Bloqueia 4 páginas** (alunos, progresso, avaliações, encarregados). |
| `POST /b2b/classrooms` | 403 (só `coordenador`) — esperado, mas confirma que o professor não cria turmas. |
| 404 | `/school/teacher-permissions`, `/teacher/student-progress`, `/teacher/milestones` |
| Validação | ✅ avaliação `POST /teacher/evaluations/{id}` exige `status`; missão exige `learning_object_id`; inscrição em desafio `{classroom_id, partner_challenge_id}` |
| Front | Falham: `/mission-templates`, `/lessons`, `/challenges/collective`, `/invitations/mine`, `/profiles/{id}`, `/notifications`, `/school/teacher-permissions` (404) e `/b2b/teacher/classrooms` (500). |

**Síntese**

| Perfil | Login | Páginas sem pedidos falhados | Bloqueio principal |
|---|---|---|---|
| Admin | ✅ | não medido por página (só testei os endpoints, ver §6.1) | Tenants, utilizadores, moedas, finanças, trilhas |
| Escola | ✅ | não medido por página (só testei os endpoints, ver §6.2) | `/school/stats`, editar/apagar turma |
| Professor | ✅ | **1 de 10** (`/teacher`) | `GET /b2b/teacher/classrooms` (500) bloqueia 4 páginas |
| Encarregado | ✅ | **1 de 17** (`/parent/activity`) | rotas do front não alinhadas com a API (`/wallets`, `/subscription`, `/tasks`, `/savings-vaults`…) |
| Parceiro | ✅ | **3 de 7 medidas** (dashboard, recompensas, relatórios; `/partner/profile` não chegou a ser medida) | `GET /partner/wallet` (500), `/partner/limits` (404) |

**Defeitos de back novos (acrescentar à secção 4.2)**

| ID | Defeito | Prio |
|---|---|---|
| BE-FIX-10 | `TeacherClassroomController::myClassrooms` — relação `pivot` inexistente (500). | **P0** |
| BE-FIX-11 | `PartnerWalletController` — utilizador `partner` sem `tenant_id`; criar tenant do parceiro no registo/seed e usar `tenant_id` ao criar a carteira. | **P0** |
| BE-FIX-12 | `StreakController::show` — 500 para utilizadores que não são criança; devolver `200` vazio. | P1 |
| BE-FIX-13 | `GET /children/{id}/summary`, `GET /school/reports`, `GET /school/dashboard` com valores fixos em vez de dados reais. | P1 |
| BE-FIX-14 | `GET /finance/wallet/balance`: `balance` e `ledger_balance` divergem (100000 vs 0). | P1 |

**Correções já aplicadas (2026-10-29, por validar após *deploy* do back)**

| Onde | Correção | Estado |
|---|---|---|
| `kivara-api` `TeacherClassroomController::myClassrooms` | `withCount('students as student_count')` em vez da relação inexistente `pivot` (BE-FIX-10) | código alterado, `php -l` OK; **falta *deploy* e teste** |
| `kivara-api` `PartnerWalletController` | `resolveTenantId()`: usa o tenant do utilizador ou cria/reutiliza um tenant `partner` com o nome do parceiro (BE-FIX-11) | idem |
| `kivara-api` `StreakController` | `show` e `record` devolvem 200 com zeros para quem não é `ChildProfile` (BE-FIX-12) | idem |
| Front, encarregado | `/tasks?parent…` → `/tasks/household`; `/allowances` → `/finance/allowances`; `/savings-vaults` e `/dream-vaults` → `/vaults` (contribuir → `/vaults/{id}/deposit`); carteira real → `/finance/wallet/balance`; `GET /subscription`, `/wallets`, `/admin/regional-prices`, `/admin/exchange-rates` com 404 tratado | **testado**: páginas do encarregado sem pedidos falhados passaram de **1 para 5 de 17** |

Ainda falham no encarregado (precisam de back ou de decisão): `/parent/consent` (o back é por filho: `/consents/{childProfileId}`), `/streaks`, `/schools`, `/wallets/budget-exceptions`, comentários de cofres, `/notifications`.

**Registos de teste criados pelo encarregado na API:** filho "Filho Teste" (`a318e230-a158-486e-acb5-970023b51453`), tarefa "Arrumar o quarto" (500 AOA), uma mesada semanal, o cofre "Bicicleta" e a recompensa "Cinema".

---

## 3. Requisitos transversais

### 3.1 Segurança e operação

| ID | Requisito | Prio | Evidência / critério |
|---|---|---|---|
| X-SEC-1 | `APP_DEBUG=false` em produção e *handler* de exceções que devolve JSON limpo `{status:"error", message}` (sem *stack trace*, caminhos, SQL). | **P0** | Hoje 404/500 devolvem trace completo e SQL (ex.: `POST /trails`). `bootstrap/app.php` tem `->withExceptions(function(){ // })` vazio. Aceitação: nenhuma resposta ≥ 400 contém `trace`, `file`, `SQLSTATE`. |
| X-SEC-2 | Rate limiting nas rotas públicas (`auth/login`, `verify-activation`, `resend-activation`, `school-applications`, webhooks) e *lockout* de login. | P0 | Aceitação: 6.º pedido/min ao login → `429`. |
| X-SEC-3 | Webhooks de pagamento (`finance/wallet/webhooks/multicaixa`, `school/subscription/webhook`, `partner/wallet/webhook`) validam assinatura/HMAC e idempotência. | P0 | Hoje públicos sem autenticação. |
| X-SEC-4 | CORS configurável por ambiente (`localhost:8080`, IP de rede, `https://localhost` e `capacitor://localhost`, domínio de produção). | P1 | Hoje só `http://localhost:8080`. |
| X-SEC-5 | Front: `Content-Security-Policy` do `index.html` gerida por ambiente (o domínio da API não pode ser fixo). | P1 | Falhou no arranque desta fase: bloqueava a nova API. |
| X-SEC-6 | Decidir **modelo de sessão**: (a) *Bearer token* (atual; `statefulApi` está desativado) ou (b) cookie `HttpOnly` Sanctum SPA como no SRS de frontend. Documentar e alinhar `api-client.ts` (hoje usa `credentials:'include'` **e** Bearer em `localStorage`). | P1 | Aceitação: um único mecanismo; nenhum token em `localStorage` se (b). |

### 3.2 Contrato da API

| ID | Requisito | Prio |
|---|---|---|
| X-API-1 | Envelope único: sucesso `{status:"success", message?, data}`; listas com `pagination{current_page,per_page,total,last_page}`; erro `{status:"error", message, errors?}`. Hoje coexistem `{data,meta}`, `{status,data,pagination}`, arrays nus (`GET /school/teachers`), respostas sem envelope (`GET /tenants/{id}`). | P1 |
| X-API-2 | Mensagens de validação traduzidas (`lang/pt`): hoje chegam chaves cruas (`validation.required`, `validation.exists`, `validation.in`). | P1 |
| X-API-3 | `404` em recurso inexistente (nunca `500`) e `405` com JSON simples. Hoje `PATCH /admin/lessons/{id}` e `/admin/subscription-plans/{id}` com ID inexistente dão 500. | P1 |
| X-API-4 | Todos os `PATCH/PUT` com `FormRequest` e rejeição de campos desconhecidos (hoje `PATCH /tenants/{id}` aceita `status:"invalid"` e ignora `is_active`). | P1 |
| X-API-5 | Convenção de papéis: **um** nome por papel. Escolher `coordinator` **ou** `coordenador` (as rotas usam as duas) e decidir se a conta "escola" é `school`, `school_admin` ou `coordinator`. Sincronizar `user.role`, Spatie e *abilities* do token. | **P0** |
| X-API-6 | Publicar OpenAPI com `requestBody`/`schema` de resposta de **todas** as rotas (hoje só o login). Existem `openapi.yaml`, `openapi-missing-endpoints.yaml` e `onboarding_openapi.yaml` por consolidar. | P1 |
| X-API-7 | Remover duplicados/aliases desnecessários (`/vaults` ×2, `/allowances` ×3, `/classrooms` ×3, `/tasks` ×2…) ou documentá-los como aliases oficiais. | P2 |

### 3.3 Multi-tenant e subscrição

| ID | Requisito | Prio |
|---|---|---|
| X-TEN-1 | O `tenant_id` do utilizador vem no `/auth/login` e no `/auth/me` (hoje só no `/me`; o front faz uma 2.ª chamada). | P1 |
| X-TEN-2 | Todas as consultas das rotas `school/*`, `teacher/*`, `b2b/*` filtram pelo tenant do token (ignorar `?tenant_id=` vindo do cliente, exceto admin). | **P0** |
| X-TEN-3 | `subscribed.tenant` (`CheckTenantSubscription`) devolve `402/403` com código estável (`SUBSCRIPTION_REQUIRED`) que o front converte em *paywall*. | P1 |

---

## 4. Backend — defeitos e lacunas verificados no código

### 4.1 Rotas declaradas sem implementação (erro 500)

| Rota | Controlador | Falta | Prio |
|---|---|---|---|
| `PATCH/PUT /classrooms/{id}` | `ClassroomController` | `update()` | P1 |
| `DELETE /classrooms/{id}` | `ClassroomController` | `destroy()` | P1 |
| `POST /b2b/evaluations/{progressId}/approve` | `MissionEvaluationController` | `approve()` | P1 |
| `POST /b2b/evaluations/{progressId}/reject` | `MissionEvaluationController` | `reject()` | P1 |

*(O front do professor usa `/b2b/evaluations`, `/teacher/submissions/{id}/approve|reject` — três caminhos diferentes para a mesma ação; ver §6.3.)*

### 4.2 Defeitos funcionais confirmados

| ID | Defeito | Prio |
|---|---|---|
| BE-FIX-1 | `GET /tenants` (público) devolve **dados fixos**: `[{id:"1",name:"Colégio de Teste"}]` (`routes/api.php`, linha ~578). O ID não existe e o ecrã de edição de tenants falha com 404. | **P0** |
| BE-FIX-2 | `POST /trails` falha com SQL: `trails.kivara_phase_id` é obrigatório (migração `2024_01_10_000001`), mas `TrailController::store` não o recebe nem o valida (usa `grade_level`). Sem trilha não há lições (`POST /admin/lessons` exige `trail_id`). | **P0** |
| BE-FIX-3 | `GET /school/stats` → 500 (`ClassroomController::stats()` espera 2 argumentos, a rota passa 1). | P1 |
| BE-FIX-4 | `GET /teacher/classrooms/{id}/lessons` → 500: tabela `classroom_lessons` não existe (falta migração). | P1 |
| BE-FIX-5 | `GET /b2b/classrooms/{id}/students` → 403 para a conta de escola (middleware `role:coordenador|teacher`, mas a conta tem role `school`). | P1 |
| BE-FIX-6 | `POST /tenants/{id}/logo` devolve sempre o mesmo URL, sem validar nem guardar ficheiro. | P1 |
| BE-FIX-7 | `PATCH /tenants/{id}` e `GET /tenants/{id}` usam formatos diferentes (`SchoolProfileController`): GET devolve `school_type`/`nif`/`status`, PATCH devolve `tenant_type`/`email`/`domain`/`is_active`. | P1 |
| BE-FIX-8 | Planos (`/admin/subscription-plans`): `type` aceita só `free|family|school|partner`; `monthly_price`/`annual_price` na leitura mas `price_monthly`/`price_yearly` ignorados em silêncio; `DELETE` devolve 200 (spec diz 204). | P2 |
| BE-FIX-9 | `routes.json` e ficheiros OpenAPI desatualizados em relação a `routes/api.php`. | P2 |

### 4.3 Módulos em falta no back

| ID | Módulo | Requisito | Prio |
|---|---|---|---|
| **BE-REG** | **Registo** | `POST /auth/register` (público): cria utilizador inativo + OTP de 6 dígitos por email; resposta `201 {requires_activation:true}` sem token. Ativação via `verify-activation` (já existe). Papéis permitidos: `parent`, `partner` (pendente de aprovação), `teacher` (convite). Aceitação: email repetido → 422; OTP inválido 5× → 429; após `verify-activation` o `GET /auth/me` devolve 200. | **P0** |
| **BE-PWD** | Recuperação | `POST /auth/forgot-password`, `POST /auth/reset-password` (token por email, expira em 60 min). O front tem as páginas (`ForgotPassword.tsx`, `ResetPassword.tsx`). | P0 |
| **BE-NOT** | **Notificações** | Existe a migração `notifications`, mas **nenhum modelo, controlador ou rota**. Criar: `GET /notifications` (paginado, `?unread=1`), `PATCH /notifications/{id}/read`, `POST /notifications/mark-all-read`, `DELETE /notifications/{id}`, `GET/PUT /notifications/settings`. `POST /admin/notifications/send` deve gravar linhas em `notifications` (hoje só "envia"). Aceitação: o sino do front (que hoje deriva avisos de outros endpoints) passa a usar a lista real. | P1 |
| **BE-TEN** | **Tenants (admin)** | Ver §6.1: listar, criar, ativar/desativar, eliminar. | **P0** |
| BE-TCH | Staff/professores | `PATCH /school/teachers/{id}/status`, `DELETE /school/teachers/{id}`, `POST /school/teachers/invite` (alias de `staff/invite`). | P1 |
| BE-TPL | Missões | `GET/POST/PATCH/DELETE /mission-templates` e `POST /missions/generate` usados pelo admin. | P1 |
| BE-USR | Utilizadores (admin) | `GET/POST/PATCH/DELETE /admin/users`, `GET/PUT /admin/users/{id}/roles`. | P1 |
| BE-CUR | Moedas | `GET/POST/PUT /admin/currencies`, `/admin/exchange-rates` (existe `GET /currencies` público). | P1 |
| BE-INV | Convites | `POST /invitations`, `GET /invitations/mine`, `POST /invitations/{id}/{accept|reject}`. | P2 |

---

## 5. Frontend — requisitos transversais

| ID | Requisito | Prio | Estado |
|---|---|---|---|
| FE-AUTH-1 | Login por role → rota inicial correta (`/admin`, `/school`, `/teacher`, `/parent`, `/partner`). | P0 | OK (admin, escola); restantes por testar |
| FE-AUTH-2 | Fluxo de **primeiro acesso**: `must_change_password:true` (o admin da API vem assim) → ecrã obrigatório de alteração; usar `POST /auth/change-password` (hoje o front chama `/auth/force-password-change`, que não existe). | P0 | Por fazer |
| FE-AUTH-3 | Ativação por OTP (`/auth/verify-activation`, `/auth/resend-activation`): ecrãs de código de 6 dígitos (hoje `/auth/verify-email` e `/auth/resend-verification`). | P0 | Por fazer |
| FE-AUTH-4 | Renovação de sessão: o back expõe `POST /auth/refresh` (aceita o *refresh token*, devolve `access_token` e `expires_in`); o `api-client` deve usá-lo no 401 e fazer logout se falhar. | P1 | Parcial |
| FE-AUTH-5 | Remover ecrãs/chamadas sem suporte no back para estas personas: 2FA (`/auth/2fa/verify`), dispositivos de confiança, `child-login`. | P2 | Por fazer |
| FE-ERR-1 | Camada única de erros: traduzir `422` (`errors[campo][]`) para mensagens de campo; `403 SUBSCRIPTION_REQUIRED` → paywall; `404` de rota inexistente → aviso "funcionalidade ainda não disponível" em vez de *toast* técnico. | P1 | Por fazer |
| FE-ERR-2 | Normalizadores centralizados `src/lib/api-adapters.ts` (planos, turmas, tenants, utilizadores) para não espalhar `?? ` por hooks. Hoje há cópias em `use-tenants`, `use-subscription`, `use-classrooms`. | P2 | Parcial |
| FE-UI-1 | Cabeçalho (navbar) e cabeçalho da sidebar fixos; só o conteúdo e o menu rolam. | P2 | **Feito** |
| FE-UI-2 | Modo escuro completo e testável (auditoria de contraste automática por página). | P2 | Admin e escola **feitos**; restantes por rever |
| FE-UI-3 | Sino de notificações com contagem real (ver BE-NOT). | P1 | Derivado; passar a real |

---

## 6. Requisitos por persona

> Os estados ⏳ nas tabelas abaixo foram verificados na secção 2.1: onde ficou ⏳ é porque o endpoint responde 200 mas só foi testado com dados vazios.

Legenda de estado: ✅ funciona · ⚠️ funciona com adaptação no front · ❌ falta no back · ⏳ não testado.

### 6.1 Super Admin (`/admin/*`)

**Páginas:** Dashboard, Tenants, Subscrições, Moedas, Auditoria, Risco, Segurança de Auth, Compliance, Escolas, Professores, Finanças, Utilizadores, Lições, Trilhas, Missões, Onboarding, Notificações, Banners.

| Área | Chamada do front | Rota no back | Estado |
|---|---|---|---|
| Planos | `/admin/subscription-plans` (CRUD) | `apiResource` | ⚠️ tipos e preços convertidos no front |
| Compliance | `/admin/compliance/stats`, `/admin/audit-logs`, `/admin/risk-flags` (+PATCH) | existem | ✅ (listas vazias) |
| Onboarding | `/admin/onboarding-analytics` | existe | ✅ |
| Banners | `/admin/login-banners` (CRUD, reorder) | `apiResource` + reorder | ⏳ (front usa `/upload`, inexistente) |
| Notificações | `/admin/notifications/send` | existe | ⏳ não testado (envio em massa) |
| Roles | `/admin/roles`, `/admin/roles/{id}/sync` | existem | ✅ (sem permissões; falta role `school`) |
| **Tenants** | `GET /admin/tenants`, `POST/PATCH /admin/tenants/{id}`, `activate/deactivate` | **não existem**; só `GET/PATCH /tenants/{id}` | ❌ |
| Candidaturas | `/admin/school-applications` | `GET /b2b/school-applications` (+approve/reject) | ⚠️ trocar caminho |
| Utilizadores | `/admin/users…`, `/admin/users/{id}/roles` | **não existem** | ❌ |
| Moedas | `/admin/currencies`, `/admin/exchange-rates` | **não existem** (só `GET /currencies`) | ❌ |
| Finanças | `/admin/wallets`, `/admin/invoices`, `/admin/stats` | **não existem** | ❌ |
| Auditoria de auth | `/admin/auth-events`, `/admin/login-lockouts`, `/admin/audit-log` | só `GET /admin/audit-logs` (plural) | ❌/⚠️ |
| Lições/trilhas | `/lessons`, `/admin/trails`, `/mission-templates` | `/admin/lessons`, `/trails` (store com defeito BE-FIX-2) | ❌ bloqueado |

**Requisitos**
- **BE-ADM-1 (P0) Tenants completos.** `GET /tenants` (admin) lista **tenants reais** com `{id,name,tenant_type,currency,is_active,subscription_tier_id,subscription_tier{id,name,type},created_at}` + filtros `type,status,search,page,per_page`; `POST /tenants`; `PATCH /tenants/{id}` validado (incluindo `is_active`); `POST /tenants/{id}/activate|deactivate`; `DELETE /tenants/{id}` (soft delete; `409` se tiver utilizadores ativos); `POST /tenants/{id}/logo` multipart (png/jpg/webp ≤ 2 MB). O `GET /tenants` público passa a `GET /public/schools` (só `id,name`). *Aceitação:* os IDs da lista funcionam em `GET/PATCH/DELETE /tenants/{id}`; renomear e reler devolve o novo nome; tenant inativo bloqueia login dos seus utilizadores (`403`).
- **FE-ADM-1 (P0)** Ligar `AdminTenants`/`AdminSchools` aos endpoints acima; tratar "sem plano" como `Plano Base`.
- **BE-ADM-2 (P1)** Utilizadores e roles (`/admin/users`, `/admin/users/{id}/roles`) com paginação e pesquisa; semear a role da escola.
- **BE-ADM-3 (P1)** Resumo financeiro: `/admin/finance/summary` (receita por moeda, pendente, assinaturas ativas), `/admin/invoices`, `/admin/wallets`. O front tem hoje valores de demonstração no dashboard.
- **BE-ADM-4 (P1)** Moedas e taxas (`/admin/currencies`, `/admin/exchange-rates`) com histórico.
- **BE-ADM-5 (P1)** Banners: upload multipart em `POST /admin/login-banners` (campo `image`), `reorder`, cliques.
- **BE-ADM-6 (P1)** Corrigir `POST /trails` (BE-FIX-2) e alinhar contrato de lições (`/admin/lessons`) com o editor (`title, description, trail_id, lesson_number, icon, category, difficulty, estimated_minutes, quiz[], memory_pairs[], puzzle, blocks[], xp_reward, kiva_points_reward, is_active, sort_order`).
- **FE-ADM-2 (P1)** Remover ecrãs sem back nesta fase (Moedas, Finanças reais) ou marcá-los como "em breve" até os endpoints existirem.

### 6.2 Escola (`/school/*`)

| Área | Rota no back | Estado |
|---|---|---|
| Dashboard | `GET /school/dashboard` | ✅ |
| Turmas | `GET/POST /school/classrooms`, `GET /classrooms/{id}` (com `students[]`) | ⚠️ campos `grade_level`/`year`/`teacher_id` convertidos no front |
| Editar/apagar turma | `PATCH/DELETE /classrooms/{id}` | ❌ 500 (BE §4.1) |
| Alunos | `GET /school/students` (paginado) | ✅ (vazio) |
| Professores | `GET /school/teachers` (array nu), `POST /school/staff/invite`, `PATCH /school/teacher-permissions/{id}` | ⚠️ |
| Desafios | `GET/POST/PATCH/DELETE /school/challenges`, `…/leaderboard` | ✅ (vazio) |
| Recompensas | `/school/rewards` (CRUD) | ✅ (vazio) |
| Subscrição | `GET /school/subscription`, `POST …/checkout` | ✅ |
| Relatórios | `GET /school/reports`, `teacher-activity`, `milestones` | ✅ (`reports` devolve valores de exemplo) |
| Perfil | `GET/PATCH /tenants/{id}` e `GET/PATCH /school/profile` | ⚠️ preferir `/school/profile` |
| Estatísticas | `GET /school/stats` | ❌ 500 |
| Lista pública de escolas | `GET /schools` (usada no login/registo) | ❌ |

**Requisitos**
- **BE-SCH-1 (P0)** Role da escola: aceitar `school`/`coordinator` nas rotas de turma/alunos (BE-FIX-5, X-API-5).
- **BE-SCH-2 (P1)** `ClassroomController::update/destroy` e `stats` (BE-FIX-3); tabela `classroom_lessons` (BE-FIX-4).
- **BE-SCH-3 (P1)** Endpoints de staff: `PATCH /school/teachers/{id}/status`, `DELETE /school/teachers/{id}`, `POST /school/teachers/invite`; `GET /school/teachers` com envelope e `pagination`.
- **BE-SCH-4 (P1)** `GET /school/reports` calculado a partir de dados reais (hoje devolve `avg_financial_score: 82.4`, `collective_savings_aoa: 2450000` fixos) e `GET /school/dashboard` idem (`progress_by_year` com valores fixos).
- **BE-SCH-5 (P1)** `GET /school/profile` / `PATCH` com o mesmo formato (BE-FIX-7); logo real (BE-FIX-6).
- **FE-SCH-1 (P1)** Usar `/school/profile` para o perfil próprio e deixar `/tenants/{id}` ao admin.
- **FE-SCH-2 (P1)** Já feito: turmas normalizadas, alunos via `GET /classrooms/{id}`. Falta: editar/apagar turma (aguarda BE-SCH-2), convidar professor (usar `/school/staff/invite`).
- **FE-SCH-3 (P2)** Registo de escola: `POST /b2b/school-applications` (público) — campos obrigatórios `name, nif, school_type, education_level, contact_email, director_name, director_email`. Validar o formulário com estes nomes.

### 6.3 Professor (`/teacher/*`)

| Fluxo | Chamada do front | Rota no back | Estado |
|---|---|---|---|
| Minhas turmas | `GET /b2b/teacher/classrooms` | existe | ⏳ |
| Progresso da turma | `GET /b2b/teacher/classrooms/{id}/progress` | existe | ⏳ |
| Fila de avaliações | `GET /b2b/teacher/classrooms/{id}/evaluations` ou `/teacher/classrooms/{id}/pending-evaluations` | existem | ⏳ |
| Avaliar | front: `POST /b2b/evaluations`, `/teacher/submissions/{id}/approve|reject` · back: `POST /teacher/evaluations/{progressId}`, `POST /school/evaluations/{progressId}`, `POST /b2b/evaluations/{progressId}/approve|reject` (sem método) | ❌ contrato dividido em 5 caminhos |
| Atribuir missão | `POST /b2b/teacher/classrooms/{id}/missions` | existe | ⏳ |
| Convidar aluno | `POST /b2b/teacher/classrooms/{id}/invite-student` | existe | ⏳ |
| Lições da turma | `GET/POST /teacher/classrooms/{id}/lessons` | existe (BE-FIX-4: tabela em falta) | ❌ |
| Progresso/marcos | front: `/teacher/student-progress`, `/teacher/milestones`, `/profiles/{id}/pedagogical-summary` | **não existem** | ❌ |
| Missões | `PATCH/DELETE /missions/{id}` | só `GET/POST /missions` | ❌ |
| Permissões | `GET /school/teacher-permissions` | **não existe** (só `PATCH …/{id}`) | ❌ |

**Requisitos**
- **BE-TCH-1 (P1)** **Um único endpoint de avaliação**: `POST /teacher/evaluations/{progressId}` com `{decision:"approved|rejected", score?, feedback}` (já existe via `SchoolEvaluationController@evaluate`). Remover `approve/reject` sem método ou implementá-los como atalhos. *Aceitação:* aprovar credita a carteira do aluno de forma idempotente e grava `teacher_feedback`.
- **FE-TCH-1 (P1)** `TeacherReviewQueue.tsx` passa a usar esse endpoint; remover `/b2b/evaluations` e `/teacher/submissions/*`.
- **BE-TCH-2 (P1)** `GET /teacher/dashboard` (turmas, pendentes, desafios a terminar) e `GET /teacher/student-progress?classroom_id=` agregados; `GET /school/teacher-permissions` (permissões do próprio professor).
- **BE-TCH-3 (P1)** CRUD completo de missões do professor (`PATCH/DELETE /missions/{id}`) e `mission-templates` (BE-TPL).
- **FE-TCH-2 (P1)** Estados vazios claros quando o professor não tem turma atribuída (conta demo tem `classrooms_count: 0`).
- **X-TCH-1 (P1)** Rotas `role:teacher` não devem excluir `school`/`coordinator` onde o front reutiliza o ecrã.

### 6.4 Encarregado (`/parent/*`) — ⏳ por testar contra a API

| Área | Chamada do front | Rota no back | Estado |
|---|---|---|---|
| Filhos | `/children` (CRUD), `/children/{id}/summary`, `/parent/children/{id}/summary` | existem | ⏳ |
| Tarefas | `/tasks/household`, `POST /tasks`, `PATCH/DELETE`, `approve`, `reject`, `suggest` | existem | ⏳ |
| Mesadas | `/finance/allowances` (+ aliases `/allowance-configs`), `process`, `send-now` | existem | ⏳ front chama `/allowances` (alias existe só `/allowances/{id}/send-now`) |
| Cofres | `/vaults` (+`deposit`, `withdraw`) | existem | ⏳ front usa `/dream-vaults` e `/savings-vaults` ❌ |
| Carteira | `/finance/wallet/balance|history|top-up` | existem | ⏳ front usa `/wallets/*` ❌ |
| Recompensas | `/rewards`, `claim`, `suggest` | existem | ⏳ |
| Pedidos de nível | `/parents/level-up-requests` (+approve/reject) | existem | ⏳ |
| Consentimentos | `GET /consents/{childProfileId}`, `POST …/update` | existem | ❌ front usa `/parent/consent*` e `/admin/consents*` |
| Insights | `POST /insights/generate` | existe | ⏳ |
| Subscrição | `/subscription/tiers|subscribe|cancel|invoices` | existem | ⚠️ front também chama `GET /subscription` e `add-extra-child` ❌ |
| Sequências/atividade | front: `/streaks`, `/weekly-summary` | só `gamification/streaks/me` | ❌ |
| Emparelhamento | `POST /family/pair` | existe (`ability:role:parent`) | ⏳ |

**Requisitos**
- **FE-PAR-1 (P0)** Alinhar os hooks do encarregado com as rotas existentes: `/wallets/*` → `/finance/wallet/*`; `/dream-vaults` e `/savings-vaults` → `/vaults`; `/allowances` → `/finance/allowances`; `/parent/consent*`, `/admin/consents*` → `/consents/{childProfileId}`.
- **BE-PAR-1 (P1)** `GET /subscription` (subscrição atual do tenant/família) e `POST /subscription/add-extra-child` (preço por criança extra).
- **BE-PAR-2 (P1)** `GET /parent/weekly-summary` (resumo semanal por filho: XP, tarefas, poupança) e `GET /streaks` (por filho) para o painel.
- **BE-PAR-3 (P1)** Pagamentos: `POST /finance/wallet/top-up` devolve referência Multicaixa; webhook seguro (X-SEC-3).
- **FE-PAR-2 (P1)** Onboarding: usar `GET/PUT /onboarding/progress` em vez de `/onboarding/steps`.
- **X-PAR-1 (P0)** O encarregado só acede aos **seus** filhos (`child_profiles.family_id`) em todos os endpoints (autorização por *policy*).
- **Teste de aceitação ponta a ponta:** registar encarregado → ativar OTP → criar filho → criar tarefa → (aprovação) → saldo do filho atualizado.

### 6.5 Parceiro (`/partner/*`) — ⏳ por testar contra a API

| Área | Chamada do front | Rota no back | Estado |
|---|---|---|---|
| Dashboard | `GET /partner/dashboard` | existe | ⏳ |
| Carteira | `GET /partner/wallet`, `POST …/top-up` | existem | ⏳ |
| Programas | front: `/partner-programs` (CRUD), `/partner/programs/{id}` DELETE | back: `/partner/programs` (GET/POST/PATCH; **sem DELETE**) | ❌ trocar caminhos |
| Desafios | `/partner/challenges` (CRUD) | existem | ⏳ |
| Participações / estado | `GET /partner/challenges/{id}/participations`, `PATCH …/{id}/status` | **não existem** | ❌ |
| Limites do plano | `GET /partner/limits` | **não existe** | ❌ |
| Relatórios ESG | `/partner/reports/metrics|chart-data|export` | existem | ⏳ |
| Convites a escolas | `/partner/programs/{id}/invite`, `/partner/invitations/accept|reject` | **não existem** | ❌ |
| Perfil | `PATCH /profiles/{id}`, `PATCH /tenants/{id}` | só `tenants/{id}` | ⚠️ |
| Registo | `POST /auth/register` | **não existe** | ❌ (BE-REG) |

**Requisitos**
- **FE-PAR-3 (P0)** Substituir `/partner-programs*` por `/partner/programs*` em `use-partner-data.ts`; exportação CSV com `Authorization` (já usa `fetch`).
- **BE-PRT-1 (P1)** `DELETE /partner/programs/{id}` (só se sem desafios ativos) e `PATCH /partner/challenges/{id}/status` (`draft|active|paused|closed`).
- **BE-PRT-2 (P1)** `GET /partner/challenges/{id}/participations` com agregados (**Zero PII**, só contagens por escola/região).
- **BE-PRT-3 (P1)** `GET /partner/limits` (nº de programas/desafios permitidos pelo plano) para o front bloquear a criação.
- **BE-PRT-4 (P2)** Convites a escolas (`invite/accept/reject`) ou retirar o fluxo do front.
- **X-PRT-1 (P0)** Relatórios nunca devolvem dados pessoais de menores (teste automático de ausência de campos `name`, `email`, `birth_date`).

---

## 7. Requisitos não-funcionais

| ID | Requisito |
|---|---|
| RNF-1 | Tempo de resposta p95 < 500 ms nas listas paginadas; paginação obrigatória (`per_page` máx. 100). |
| RNF-2 | Idempotência (`Idempotency-Key`) em `finance/transactions`, `top-up`, aprovação de tarefas e avaliações. |
| RNF-3 | Auditoria: toda a ação de escrita de admin/escola/parceiro gera linha em `audit_logs` (hoje a lista está vazia). |
| RNF-4 | Testes: back com PHPUnit/Pest por rota (autorização por role e por tenant, validação, formato); front com Vitest (hooks e adaptadores) e testes de contraste do modo escuro. |
| RNF-5 | CI do front: `npm audit --audit-level=high`, `tsc`, testes e *build* (já a passar após a atualização de dependências). CI do back: `composer audit`, testes, `php artisan route:list` sem erros. |
| RNF-6 | Tipagem: gerar tipos do front a partir do OpenAPI do back (X-API-6) em vez de interfaces escritas à mão. |
| RNF-7 | Acessibilidade e *dark mode* AA (contraste ≥ 4.5:1) nas páginas das 5 personas. |
| RNF-8 | Observabilidade: *request id* nas respostas e logs sem dados pessoais. |

---

## 8. Plano de execução sugerido

**Fase A — Back desbloqueia (1.ª semana)**
1. X-SEC-1 (`APP_DEBUG`, handler JSON) · X-API-5 (papéis) · BE-FIX-1/2 (tenants reais, `POST /trails`) · BE-REG + BE-PWD · §4.1 métodos em falta.
2. Testes automáticos por rota (RNF-4) para o que for corrigido.

**Fase B — Front consome e valida por persona (em paralelo com A)**
1. Escola e admin (já parcialmente feitos) → fechar lacunas da §6.1/6.2.
2. **Testar encarregado, parceiro e professor** com contas de teste autorizadas e alinhar hooks (FE-PAR-1/3, FE-TCH-1).
3. Fluxos de autenticação: FE-AUTH-2/3 (primeiro acesso, OTP).

**Fase C — Completar módulos (2.ª–3.ª semana)**
BE-NOT (notificações), BE-ADM-2/3/4, BE-SCH-3/4, BE-TCH-2/3, BE-PAR-1/2, BE-PRT-1/2/3.

**Fase D — Endurecimento**
Webhooks (X-SEC-3), rate limiting, CORS por ambiente, OpenAPI completo e tipos gerados, auditoria.

### 8.1 Critério de "feito" por persona
- Login e rota inicial corretos.
- Todas as páginas carregam sem erro 404/500 no console com dados vazios **e** com dados de teste.
- Ações de escrita principais testadas ponta a ponta (criar → ler → editar → remover/arquivar).
- Contraste AA em modo claro e escuro (auditoria automática sem falhas).

---

## 9. Dados e contas de teste

| Persona | Conta | Estado |
|---|---|---|
| Admin | `admin@kivara.co.ao` (`must_change_password: true`) | testada |
| Escola | `school@kivara.ao` (role `school`, tenant `a3152155-ee0f-4b4c-84cd-7b53133032b4`) | testada |
| Professor | `teacher@kivara.ao` (0 turmas) | **por autorizar/testar** |
| Encarregado | `parent@kivara.ao` | **por autorizar/testar** |
| Parceiro | `partner@kivara.ao` | **por autorizar/testar** |

**Registos de teste criados na API:** planos "Plano Base" (`a3153d31-12f4-4a75-a097-e130a1e37732`, `free`) e "Familiar Premium" (`a3153cf9-2cc6-49cb-a9a2-c3bbb66e7acb`, `family`); turma "Turma 3A" (`a3153ed2-fdb7-43bc-b1f0-297c508d8aed`, código `KVR-E7JJA`).

---

## 10. Decisões em aberto

1. **Sessão:** Bearer token (atual) ou cookie `HttpOnly` Sanctum? (X-SEC-6)
2. **Papéis:** nome oficial para direção de escola (`school`, `school_admin`, `coordinator`/`coordenador`) e para o professor com permissões de coordenação. (X-API-5)
3. **Criação de tenants:** `POST /tenants` direto, ou só via aprovação de `b2b/school-applications`? (BE-ADM-1)
4. **Notificações:** só *in-app* nesta fase, ou também Expo Push/e-mail? (BE-NOT)
5. **Convites a escolas por parceiros:** manter ou retirar do front? (BE-PRT-4)

---

## 11. Admin — CRUD de Tenants, Escolas e Utilizadores (implementado em 2026-10-29)

**Back (`kivara-api`)** — novas rotas sob `/api/v1/admin` (middleware `user.role:admin`, que valida a coluna `users.role` ou papéis Spatie; as rotas `ability:role:*` anteriores nunca passavam porque o login só emite tokens com a habilidade `access-api`):

| Recurso | Rotas |
|---|---|
| Tenants | `GET/POST /admin/tenants`, `GET/PATCH/DELETE /admin/tenants/{id}` (DELETE devolve 409 se houver utilizadores), `POST …/activate`, `POST …/deactivate`, `PATCH …/subscription`, `POST …/agreement`, `POST …/send-credentials`, `POST …/reassign-admin`, `GET …/members?role=`, `GET …/classrooms`, `GET …/students` |
| Utilizadores | `GET/POST /admin/users`, `GET/PATCH/DELETE /admin/users/{id}`, `POST …/block`, `POST …/unblock`, `POST …/resend-activation` |
| Painel | `GET /admin/stats` (tenants, utilizadores, crianças, carteiras, DAU, tarefas, volume do dia, sparkline 7 dias) |
| Candidaturas | `POST /b2b/school-applications/{id}/approve` passa a criar o tenant, o administrador da escola (`coordinator`) e enviar o email; `status` deixa de ser aceite no `POST` público (era *mass assignment*) |
| Público | `GET /tenants` devolve escolas ativas reais (antes era um *mock*) |

- Migration `2026_10_29_100000_add_profile_fields_to_tenants_table` (NIF, tipo, nível, país, província, cidade, morada, telefone, website, direção, `logo_url`, `agreement`).
- **Email:** `App\Support\AccountProvisioner` gera palavra-passe temporária + OTP de 6 dígitos (24 h), marca `must_change_password` e envia `AccountActivationMail`. A palavra-passe nunca é devolvida pela API; as respostas trazem `email_sent`. Falha de envio não aborta a criação (devolve `email_sent:false`; `send-credentials`/`resend-activation` devolvem 502).
- Proteções: não se pode bloquear/eliminar a própria conta nem o último admin ativo; bloquear revoga os tokens.
- Aliases Sanctum `ability`/`abilities` registados em `bootstrap/app.php`.
- Testes: `tests/Feature/AdminCrudTest.php` (4 testes, 74 asserções, SQLite em memória). Os 12 testes antigos (`AuthTest`, `TransactionTest`) falham porque `User` não usa `HasFactory`.

**Front** — `use-tenants.ts` (`/admin/tenants`, ativar/desativar por POST, `useDeleteTenant`), `AdminTenants` (botão eliminar), `AdminSchools` (membros/turmas/alunos por `/admin/tenants/{id}/…`, candidaturas via `/b2b/school-applications`, sem palavra-passe temporária no `localStorage`), `AdminUsers` (paginação, filtro de estado, estatísticas e último acesso reais; botão reenviar ativação; corrigido o bug em que a função `createForm` era ofuscada pelo estado homónimo e o formulário de edição nunca era preenchido), `AdminDashboard` (cartões e Top Escolas reais). Gráficos de receita/planos/progresso e o feed de atividade continuam com dados de demonstração.

**Por fazer / a decidir:** `QUEUE_CONNECTION=sync` e `MAIL_MAILER=log` no `.env` local — em produção configurar SMTP e, se a fila deixar de ser `sync`, correr *worker*; ecrã de ativação OTP/troca de palavra-passe no front (FE-AUTH-2/3); `waitlist` de candidaturas não existe na API.

---

## 12. Admin — Finanças, Subscrição e Moeda (implementado em 2026-10-29)

**Back (`kivara-api`)** — migration `2026_10_29_110000_add_finance_module_fields` (campos em `invoices`, congelamento em `wallets`, tabela `regional_prices`, moedas AOA/USD/EUR por omissão). Rotas sob `/api/v1/admin` (`user.role:admin`):

| Módulo | Rotas |
|---|---|
| Moedas | `GET/POST /admin/currencies`, `GET/PUT/PATCH/DELETE /admin/currencies/{code}`, `POST …/toggle-active` (409 se a moeda estiver em uso por tenants/carteiras/taxas) |
| Taxas de câmbio | `GET/POST /admin/exchange-rates`, `PUT/PATCH/DELETE /admin/exchange-rates/{id}` (par único, `base_currency`/`target_currency`) |
| Planos | `/admin/subscription-plans` (já existia) + `tenant_count` por plano; DELETE devolve 409 se houver tenants com o plano |
| Preços regionais | `GET/POST /admin/regional-prices`, `PUT/DELETE …/{id}` (único por plano+moeda) |
| Faturas | `GET/POST /admin/finance/invoices`, `GET/PATCH/DELETE …/{id}`, `GET /admin/finance/invoices/summary` (por estado, moeda, tipo e últimos 6 meses). Faturas pagas não se eliminam nem mudam de valor |
| Carteiras | `GET /admin/wallets?is_frozen=`, `POST …/{id}/freeze`, `POST …/{id}/unfreeze`, `GET /admin/profiles?ids=` |
| Painel | `GET /admin/stats` passa a incluir `money_supply`; `GET /admin/risk-flags` aceita `unresolved` e `limit` e devolve `severity` |
| Público | `GET /currencies` devolve as moedas ativas reais (antes *mock*) |

- Testes: `tests/Feature/AdminFinanceTest.php` (5 testes, 96 asserções). Em conjunto com `AdminCrudTest`: 9 testes, 170 asserções.
- **Front:** `AdminCurrencies` (eliminar moeda e taxa), `AdminSubscriptions` (separador de facturas real, contadores reais, confirmação de eliminar plano que não era renderizada, ícone de eliminar), `AdminFinance` (faturas reais com filtros, paginação, criar/estado/eliminar, KPIs e gráficos reais na moeda base AOA, descongelar carteiras), `CurrencyDisplay` usa `/currencies`.
- **Limitações:** os KPIs e gráficos são calculados só na moeda base AOA (sem conversão entre moedas); `plans`/`subscriptions` (B2B) e `subscription_plans` (admin) continuam a ser tabelas separadas e as faturas não ligam a `plans`; congelar carteira existe na API mas não tem botão no front; simulador de receita continua comentado na página.

---

## 13. Pendências do SRS resolvidas (2026-10-29)

| ID | Estado | O que foi feito |
|---|---|---|
| X-SEC-1 | **Feito** | `bootstrap/app.php` renderiza erros da API como `{status:"error", message, errors?}` (404, 405, 401, 403, 422, 429, 500) sem *trace*, ficheiro nem SQL. Em produção manter `APP_DEBUG=false` (os detalhes ficam só nos logs). |
| X-SEC-2 | **Feito** (auth) | Limitadores nomeados por rota e por email: login 10/min, registo 5/min, OTP 5/min, reenvio de OTP 3/min, esqueci-palavra-passe 3/min, repor 5/min. *Nota:* `throttle:N,M` partilha o contador entre rotas — usar sempre limitadores nomeados. Ainda sem limite: `school-applications` e webhooks. |
| X-API-2 | **Feito** | `lang/pt/validation.php`: mensagens de validação em português com nomes de campos traduzidos. |
| X-API-3 | **Feito** | `ModelNotFoundException` → 404 JSON (nunca 500). |
| X-API-5 | **Feito** (middleware) | `user.role` unifica `coordinator`/`coordenador`/`school`/`school_admin` e lê `users.role` ou papéis Spatie; substitui todas as rotas `role:` (24) e `ability:role:*`. |
| X-TEN-1 | **Feito** | `/auth/login` devolve `tenant_id` e `must_change_password` (em `data` e em `data.user`). |
| X-TEN-2 | **Feito** (turmas) | `ClassroomController` filtra pelo tenant do token (admin vê todos); outra escola recebe 404. A avaliação de submissões só aceita alunos de turmas da escola do professor. Por rever: `school/students`, `school/challenges`, `teacher/*`. |
| BE-REG | **Feito** | `POST /auth/register` (`parent`\|`partner`): conta inativa + tenant/família, OTP de 6 dígitos por email, sem token. Parceiro **não** fica pendente de aprovação (limitação). Login de conta inativa → 403 `account_not_activated`/`account_inactive`. |
| BE-PWD | **Feito** | `POST /auth/forgot-password` (resposta idêntica exista ou não o email) e `POST /auth/reset-password` (token com hash, 60 min, uso único, revoga sessões). |
| — | **Corrigido** | `POST /auth/resend-activation` permitia a qualquer pessoa **repor a palavra-passe de qualquer conta ativa**. Agora só toca em contas com ativação pendente; contas de auto-registo mantêm a palavra-passe e recebem só um novo OTP. |
| — | **Corrigido** | Utilizadores suspensos (`is_active=false`) conseguiam iniciar sessão. |
| BE-NOT | **Feito** | `GET /notifications` (`?unread=1`, paginado, `unread_count`), `PATCH /notifications/{id}/read`, `POST /notifications/mark-all-read`, `DELETE /notifications/{id}`, `GET/PUT /notifications/settings` (coluna `users.notification_settings`). `POST /admin/notifications/send` já gravava em `notifications`. |
| BE-FIX-2 | **Feito** | `POST /trails` aceita `kivara_phase_id` (por omissão 1); migration com `grade_level`, `is_mandatory`, `is_published`, `version` (o controller já os usava). |
| BE-FIX-3 | **Feito** | `GET /school/stats` → `ClassroomController::schoolStats` com dados reais; `GET /classrooms/{id}/stats` deixou de devolver números fixos (0.78, 156, 200…). |
| BE-FIX-4 | **Feito** | Migration `classroom_lessons`. |
| BE-FIX-5 | **Feito** | Coberto pelo middleware `user.role`. |
| §4.1 | **Feito** | `PATCH/PUT/DELETE /classrooms/{id}` (+ `/school/classrooms/{id}`; só `coordinator`\|`admin`) e `POST /b2b/evaluations/{progressId}/approve\|reject`. |
| FE-AUTH-2 | **Feito** | Login com `must_change_password` mostra o ecrã obrigatório e usa `POST /auth/change-password` (o endpoint `/auth/force-password-change` não existe); depois redireciona para o painel do papel. |
| FE-AUTH-3 | **Feito** | `VerifyEmail` passou a ecrã de OTP (`verify-activation`/`resend-activation`); `RegistrationSuccess` leva a esse ecrã. O registo envia `name` e `password_confirmation` e já não regista a palavra-passe na consola. |
| FE-UI-3 | **Feito** | O sino usa `/notifications` real (o fallback derivado só ativa se a API devolver 404). |

**Ainda em aberto:** X-SEC-3 (HMAC nos webhooks), X-SEC-4 (CORS por ambiente), X-SEC-6 (modelo de sessão), X-API-1/4/6/7, BE-FIX-6/7/8/9, BE-TCH, BE-TPL, BE-INV, `GET /teacher/classrooms/{id}/progress|evaluations` (seleciona `users.*` de uma relação de `child_profiles`), FE-AUTH-4/5, FE-ERR-1/2, aprovação de parceiros no registo, e a rota `/force-password-change` só existe para sessões sem utilizador.

**Testes:** `AuthFlowTest` (6), `SchoolAndNotificationsTest` (4), `AdminCrudTest` (4), `AdminFinanceTest` (5). `User` ganhou `HasFactory`; continuam a falhar 7 testes antigos (`WalletTest`, `TransactionTest`, 1 de `AuthTest`) por falta de fábricas dos restantes modelos.

---

## 14. Escola e Professor — segurança, webhooks e fila de avaliação (2026-10-29)

| ID | Estado | O que foi feito |
|---|---|---|
| X-SEC-3 | **Feito** | Os três webhooks de pagamento (`finance/wallet/webhooks/multicaixa`, `school/subscription/webhook`, `partner/wallet/webhook`) estavam **públicos e sem autenticação** (qualquer pessoa podia marcar um carregamento como concluído). Passam a exigir `X-Kivara-Signature` = HMAC-SHA256 do corpo com `PAYMENT_WEBHOOK_SECRET` (prefixo `sha256=` opcional). Sem segredo configurado respondem **503** (falha fechada); assinatura errada → 401; limite 60/min. |
| X-SEC-4 | **Feito** | CORS por ambiente: `CORS_ALLOWED_ORIGINS` (lista) ou, por omissão, `FRONTEND_URL` + `localhost:8080`, `127.0.0.1:8080`, `https://localhost`, `capacitor://localhost`. |
| X-TEN-2 | **Feito** | Desafios (`school/challenges`): nova coluna `partner_challenges.tenant_id`; antes qualquer escola via, editava e apagava os desafios de todas e podia associar turmas de outra escola. Perfil da escola (`GET/PATCH /tenants/{id}`, logótipo): cada utilizador só acede ao seu tenant (404 nos restantes) e só a direção/admin altera (403 ao professor). |
| BE-FIX-6 | **Feito** | `POST /tenants/{id}/logo` guarda o ficheiro e grava `logo_url`; sem ficheiro → 422 (já não devolve um URL fixo). |
| BE-TCH | **Feito** | `PATCH /school/teachers/{id}/status` (revoga sessões ao suspender), `DELETE /school/teachers/{id}` (desliga das turmas, retira do tenant, suspende), `PATCH /school/teachers/{id}/classrooms` (turmas da própria escola), `POST /school/teachers/invite`. Só `coordinator`/`admin`; nunca a própria conta. |
| Professor | **Feito** | `GET /b2b/teacher/classrooms/{id}/progress` e `/evaluations` e `POST …/missions` rebentavam (alunos adolescentes tratados como `child_profiles`; coluna `display_name` inexistente). `Classroom` ganhou `teenStudents()`/`childStudents()`; `GET /b2b/teacher/classrooms` inclui os alunos. `inviteStudent` filtrava `users.name` em vez de `users.role`. |
| Modelo | **Corrigido** | `user_progress.child_profile_id` era `NOT NULL`: **nenhum adolescente podia ter progresso nem submissões**. Migration torna-o opcional e cria `UNIQUE(user_id, learning_object_id)`. |
| Front | **Feito** | `TeacherReviewQueue` deixou de usar dados de demonstração e de engolir erros: lê as submissões reais de todas as turmas e aprova/rejeita em `POST /b2b/evaluations/{id}/approve\|reject`. O contador do cabeçalho e o *badge* do menu vêm de `usePendingEvaluationsCount`; removidos os indicadores fictícios ("< 2 min", "+12 Avaliadas Hoje", badge fixo 5). |

**Verificado no browser:** professor entra, vê a submissão real "Joao Aluno – Turma 9A", aprova com nota; a BD fica `approved`, `total_xp_earned=120`, `teacher_feedback`, `reviewed_by`, e o aluno recebe uma notificação.

**Testes:** `WebhookSecurityTest` (4), `TeacherFlowTest` (6). Total back: 34 testes novos/atualizados desde a secção 11.

**Ainda em aberto:** `school/teachers` `updatePermissions` continua a ser um *stub* (devolve sucesso sem gravar); desafios de parceiros (`/partner/*`) não têm isolamento revisto; aprovação de parceiros no registo; X-SEC-6 (modelo de sessão), X-API-1/4/6/7, BE-FIX-7/8/9, BE-TPL, BE-INV; o ecrã de ativação do onboarding do professor chama `/onboarding/steps`, `/streaks/activity` e `/challenges/collective` (404 na API); `TeacherSchoolProfile` usa `POST /school/teachers` e `PATCH …/deactivate`.

---

## 15. Parceiros (2026-10-29)

| ID | Estado | O que foi feito |
|---|---|---|
| Isolamento | **Corrigido (grave)** | Os 5 controllers de parceiro resolviam o parceiro com `Partner::first()` quando o utilizador não tinha `partner_id`: qualquer parceiro novo veria a carteira, os programas e os desafios do **primeiro parceiro** (e o admin criava um "Banco Parceiro KIVARA"). Passa a existir `PartnerContext::resolve`: o parceiro do utilizador; contas antigas sem parceiro recebem um próprio e aprovado; o admin tem de indicar `partner_id`. |
| Registo | **Feito** | `POST /auth/register` com `role=partner` cria a conta inativa + `Partner` em `pending` (NIF, setor, cidade, direção…) + OTP. |
| Aprovação | **Feito** | Migration `partners`: `status` (`pending\|approved\|rejected`, existentes = `approved`), dados institucionais, `rejection_reason`, `reviewed_at/by`. Middleware `partner.approved`: enquanto não aprovado → 403 `partner_pending_approval`/`partner_rejected`; suspenso → `partner_suspended`. `GET /partner/status` (acessível antes da aprovação). |
| Admin | **Feito** | `GET /admin/partners`, `GET/PATCH/DELETE /admin/partners/{id}` (DELETE → 409 se tiver programas/desafios), `POST …/approve`, `POST …/reject {reason}` (email `PartnerDecisionMail` nos dois casos, revoga sessões ao rejeitar), `PATCH …/active` (suspender/reativar). Front: nova página **Admin → Parceiros** (`/admin/partners`). |
| Carregamentos | **Corrigido (grave)** | `ledger_entries.entry_type` era um enum sem `top_up`: **todos os carregamentos (parceiros e encarregados) falhavam ao liquidar**. Migration passa a coluna a texto. |
| Programas | **Feito** | `DELETE /partner/programs/{id}` (409 se tiver desafios; devolve o orçamento à carteira); concluir (`status=completed`) devolve o orçamento por cativar; campos `program_type`, `children_target`, `starts_at`, `ends_at`. |
| Desafios | **Feito** | Estados `draft\|active\|scheduled\|completed\|cancelled`; desafio cancelado liberta o prémio cativado; `PATCH …/status`; `GET …/participations` (turmas inscritas e progresso, sem dados pessoais). |
| Limites | **Feito** | `GET /partner/limits` (antes 404 e o front usava 2 programas / 50 crianças): `config/partners.php` (`PARTNER_MAX_PROGRAMS`, `PARTNER_MAX_CHILDREN`). |
| Front | **Feito** | `use-partner-data` com adaptadores API ↔ front (já não tenta `/partner-programs`). Removidos os 3 programas fictícios de `PartnerPrograms`. `PartnerWallet` deixou de usar saldo local (150 000), extrato fictício e "confirmação" simulada no browser: lê `/partner/wallet`, a referência bancária é gerada pela API e o saldo só sobe quando o webhook confirma. `PartnerApprovalGate` mostra "Candidatura em análise / não aprovada / conta suspensa". `PartnerRegister` já não mostra sucesso quando a API falha e leva ao ecrã de ativação por código. |

**Verificado no browser:** registo → ativação por OTP → login bloqueado em "Candidatura em análise" → admin aprova (email enviado) → carteira a 0 → referência gerada → webhook sem assinatura/com assinatura errada = 401, correto = +1 000 000 KVC → programa 600 000 KVC (carteira 400 000) → desafio 75 000 → concluir/eliminar → eliminar programa devolve 600 000.

**Testes:** `PartnerFlowTest` (3 testes, 82 asserções).

**Ainda em aberto:** convites a programas (`/partner/programs/{id}/invite`, `/partner/invitations/*`) e `PartnerRewards`, `PartnerReports` (séries e distribuição geográfica fictícias), `PartnerSubscription` (faturas fictícias), `PartnerDashboard`; `PartnerProfile` chama `PATCH /profiles/{id}` e `/tenants/{id}`; o orçamento alocado de um programa não é editável (só concluir/eliminar); `ProgramInviteDialog` exige `partnerTenantId` (erro de tipos antigo).

## 16. Encarregado (2026-11-01)

| ID | Estado | O que foi feito |
|---|---|---|
| Transações | **Corrigido (grave)** | `GET/POST /finance/transactions` aceitava qualquer `wallet_id`: um encarregado via o extrato e **criava créditos ilimitados** em carteiras de outras famílias. Passa a existir `Wallet::ownedBy` (carteira do utilizador + dos seus filhos); só o admin cria créditos/ajustes, o dono regista apenas débitos `purchase\|donation\|vault_deposit`; chave de idempotência de outra família → 409. |
| Posse dos filhos | **Corrigido (grave)** | `children/{id}/summary`, `consents/{id}` (+`/update`), `parents/level-up-requests/{id}` e `POST /missions` não validavam o dono: qualquer encarregado lia/alterava dados de filhos alheios e criava missões em filhos alheios. Agora 404 fora da própria família; `summary` devolve contagens reais (lições, missões, XP, ofensiva) em vez de zeros. |
| Escola do filho | **Corrigido** | `POST /children` aceitava `school_tenant_id` arbitrário (associar o filho a qualquer escola); só o admin; a ligação à escola faz-se pelo código de convite da turma. |
| Consentimentos | **Feito** | `GET/POST /parent/consent`, `GET/PUT /parent/consent/{id}`, `POST /parent/consent/{id}/revoke` (antes 404; a página mostrava sempre 0). Migration `consents`: `document_version`, `method`, `consent_data`, `consented_at`, `expires_at`, `revoked_at/by`, `revocation_reason`. Estados `granted\|revoked\|expired`; conceder de novo reativa o mesmo registo. |
| Ofensivas | **Feito** | `GET /parent/streaks` (antes `/streaks?profiles=` → 404 engolido): ofensiva atual, recorde e dias ativos por filho. |
| Perfil | **Feito** | `PATCH /auth/me` (nome, telefone, avatar, país, género, idioma; papel/e-mail/tenant imutáveis) + colunas em `users`; a moeda da família segue o país (`currency`, só o tenant familiar do próprio). O front chamava `PATCH /profiles/{id}` e `PATCH /admin/tenants/{id}` (403 para encarregado). |
| Exportação | **Feito** | `POST /parent/children/{id}/export` (perfil, consentimentos, movimentos, ofensiva, tarefas); o front chamava `/admin/export-user-data` (403). |
| Carteira (front) | **Feito** | `ParentWallet` deixou de usar saldo local (65 000), extrato fictício, indicadores fixos, telefone pré-preenchido, referência bancária estática e "confirmação" simulada: lê `/finance/wallet/balance` e `/history`, envia `POST /finance/wallet/top-up` (Multicaixa Express) e só mostra sucesso quando o saldo sobe após o webhook. |
| Front (outros) | **Feito** | `use-parent-consents` (parâmetros de paginação eram ignorados), `ParentConsent` (revogação enviava `consentId` em vez de `id`; exportação usa o endpoint do encarregado), `ParentProfile`, `ParentChildrenStreaks`. |

**Verificado no browser:** login encarregado → carteira a 0 Kz → carregar 25 000 Kz (intent `pending` na BD) → webhook assinado → saldo 25 000 Kz / 250 000 KVC e aviso de sucesso → página de consentimentos mostra o registo real → conceder + revogar pela interface (BD: `is_granted=0`, `revoked_at`, motivo) → `PATCH /auth/me`, `/parent/streaks` e exportação respondem com dados reais.

**Testes:** `ParentSecurityTest` (3 testes) e `ParentFamilyTest` (3 testes); suite: 43 passam, 7 antigos falham (factories em falta).

**Ainda em aberto:** exceções de orçamento, comentários nos cofres de sonhos e resumo semanal (sem endpoint); `/parent/subscription`; `ParentChildren` com erros de tipos antigos (`addExtraChild`); 404 do dashboard em `/stats/emission`, `/wallets`, `/onboarding/steps`, `/streaks/activity`; `use-parent-real-wallet` (carregamento por comprovativo via `/wallets/transactions`) continua sem back; depósito por referência bancária indisponível para encarregados (só Multicaixa Express).

## 17. Encarregado — todos os módulos do menu ligados à API (2026-11-01)

Auditoria: 28 chamadas do portal do encarregado não tinham rota no back, e **9 páginas mostravam dados fictícios quando a API devolvia vazio** (missões "Demo Aprendiz", atividade, recompensas, sonhos, faturas, insights, estatísticas de aprendizagem), o que fazia parecer que estavam ligadas.

| Módulo | Estado | O que foi feito |
|---|---|---|
| Painel | **Feito** | `GET /auth/me` passa a devolver `household_id` (sem ele o front desativava transações, guardiões e resumo); `GET /wallets` (+`/{id}/balance`, `/{id}/transactions`, em KVC), `GET /stats/emission` (limite mensal `KIVARA_EMISSION_LIMIT_KVC`), `POST /weekly-summary`. Saldo rotulado KVC (estava "AOA"). |
| Missões | **Feito (grave)** | A página só mostrava um aviso de sucesso e **nunca chamava a API**. Agora cria, inicia, conclui e elimina. Back: `PATCH/DELETE /missions/{id}`, `POST /missions/{id}/start`, `…/complete` (emite a recompensa KVC para a carteira da criança no ledger, soma XP, respeita o limite mensal de emissão, sem pagamento duplo), `POST /missions/from-template`, `GET /mission-templates`; migration `missions` (tipo, meta, XP, datas). |
| Atividade / Relatórios | **Feito** | `GET /households/{id}/transactions` (paginado, 404 fora da família); relatórios usam `/parent/children/{id}/summary` em vez de números inventados; o filtro por criança nunca funcionava (`childProfileId`). |
| Recompensas, Sonhos | **Feito** | Removidos os fallbacks fictícios. Comentários nos cofres: `GET/POST/DELETE /dream-vaults/{id}/comments`. Exceções de orçamento: `GET/POST /wallets/budget-exceptions`, `PATCH …/{id}` (aprovar debita a carteira da criança). |
| Insights | **Feito** | Removidos os textos fabricados por nome de criança ("completou 3 lições…"); `POST /insights/generate` aceita `childProfileId`, devolve `emoji` e o tipo `attention`. |
| Subscrição | **Feito (grave)** | A página simulava o pagamento (confetti + "renovada" sem chamar nada), com preços fixos (3 500/35 000 Kz) e expiração inventada ("15 de Outubro"). Agora: `GET /subscription` (plano real ou Free), `POST /subscription/subscribe` **debita a carteira do encarregado** e cria fatura paga (saldo insuficiente → 422, nada muda), `cancel`, `add-extra-child` (vaga extra paga), faturas reais; o limite de crianças do plano passa a ser aplicado em `POST /children` (`child_limit_reached`). Migration: `tenants` (período, fim, vagas extra), `subscription_plans.extra_child_price`, campos das faturas. |
| Crianças / Guardiões | **Feito** | `POST /households`, `GET /households/{id}/guardians`, `POST …/invite` (cria 2.º encarregado com credenciais por e-mail; só o principal), `DELETE …/guardians/{id}`. `ParentChildren` chamava `addExtraChild()` num hook que devolvia uma mutação (rebentava ao clicar). |
| Outros | **Feito** | `POST /streaks/activity` e `GET /onboarding/steps` (404 em todas as páginas). `TransactionController` passa a manter o saldo materializado. |

**Verificado no browser (BD sqlite local):** criar missão pela interface → iniciar → concluir: carteira da criança +5 Kz (50 KVC) e XP 40→55 com lançamento `mission_reward`; subscrição anual pela interface: carteira 25 000→20 000 Kz, fatura paga `INV-…`, tenant com plano e fim a 01/11/2027, página passa a "Plano Familiar Premium"; atividade e painel mostram os movimentos reais.

**Testes:** `ParentModulesTest` (4 testes, 90 asserções); suite: 47 passam, 7 antigos falham (factories em falta).

**Ainda em aberto:** `ParentSupport` tem contactos estáticos (`+351 900 000 000`, a substituir pelos reais; sem endpoint); `ParentChildren` mantém erros de tipos antigos (`CoinDisplay className`, `t()` com parâmetros); o preço da criança extra é 0 até o admin o definir no plano (campo ainda não exposto em Admin → Subscrição); `use-parent-real-wallet` (comprovativo) sem back; páginas mostram o estado vazio durante o carregamento (já não há dados falsos a mascarar a espera); notificações/`/profiles/{id}` de outras personas.

## 18. Escola, Professor e Assistente (2026-11-02)

Auditoria igual à do encarregado: **o próprio back fabricava números** (painel da escola devolvia 85 % de engagement, XP `($i*1200+1500)` e progresso `mt_rand`; relatórios devolviam 82,4 / 89,1 / 2 450 000 Kz / 1 420 missões e XP aleatório) e **8 páginas mostravam dados fictícios** quando a API vinha vazia.

| Módulo | Estado | O que foi feito |
|---|---|---|
| Painel e relatórios da escola | **Feito (grave)** | `GET /school/dashboard` e `/school/reports` calculam tudo a partir do progresso real (0 quando não há atividade): conclusão, pontuação média, poupança coletiva (carteiras dos alunos), curva dos últimos 6 meses, progresso por ano/turma, ranking docente com tempo médio de aprovação. `GREATEST()` (só MySQL) substituído por `CASE`. `class_progress` traz lições/missões/em risco por turma; `teacher-activity` traz turmas, aulas, XP, conclusão e último acesso; `/school/milestones` devolve a lista de marcos reais. Front: `SchoolReports` sem `MOCK_*`, curva real, exportação CSV real (PDF = impressão), faixas "+34 %" / "~12 h poupadas" / "Prof. Gabriel" removidas. |
| Subscrição B2B | **Feito (grave)** | A página simulava o pagamento (confetti + "renovada" sem API), com preços/expiração fixos. Agora: planos e faturas reais de `/school/subscription`, `POST /school/subscription/checkout` (Multicaixa Express ou referência), e só mostra sucesso quando o webhook liquida a fatura. `SchoolCheckoutScreen` lê os planos da API e encaminha para esse ecrã. |
| Professores / Permissões | **Feito (grave)** | `updatePermissions` era um stub que devolvia o que recebia. Agora grava (`users.teacher_permissions`), `GET /school/teacher-permissions` (coordenador: todos; professor: o próprio), e as permissões são **aplicadas**: `can_assign_missions` (atribuir missões) e `can_assign_challenges` (criar desafios) → 403. Só coordenador/admin alteram. |
| Lições | **Feito** | `GET /lessons` e `GET /trails/{id}/lessons` não existiam e o front caía nas lições de demonstração embutidas; novo catálogo real (`useCatalogLessons`) e "Atribuir à turma" chama `POST /classrooms/{id}/lessons` (antes só mostrava "atribuída com sucesso"). |
| Painel do professor | **Feito** | `GET /teacher/dashboard`: turmas (progresso, missões ativas), fila de avaliação e radar (inativos há 7+ dias, tentativas repetidas, conquistas); aprovar/rejeitar pelo painel avalia de facto. `GET /teacher/student-progress`, `/teacher/milestones`, `GET /teacher/students/{id}` (ficha pedagógica sem saldos). |
| Avaliações | **Corrigido (grave)** | `POST /teacher\|school/evaluations/{id}` escrevia a coluna inexistente `score` (500) e inventava saldos (150,00); passa a delegar no fluxo `/b2b/evaluations` já testado. |
| Missões do professor | **Feito** | `/missions` passa a servir também professores (alunos das suas turmas) e coordenadores (da escola), com a permissão `can_assign_missions`. |
| Desafios | **Feito** | O hook "coletivo" chamava `/challenges/collective` (404); usa `/school/challenges` com progresso por turma. |
| Mensagens | **Feito** | `POST /notifications` (professor/coordenador → turma, aluno + encarregado, ou encarregado) com isolamento por turma; `TeacherStudents`/`TeacherGuardians` usam-no; `GET /classrooms/{id}` devolve o encarregado e `display_name` de cada aluno. |
| Perfil do professor | **Feito** | `PATCH /auth/me` (em vez de `/profiles/{id}`); equipa da escola via `/school/teachers/invite` e `…/status`. A hidratação do utilizador após guardar o perfil rebentava em silêncio (`mapKivaraPhaseId`/`ProfileData` inexistentes). |
| **Assistente** | **Feito** | Era só um nome ("assistente" no nome do utilizador) que escondia menus. Agora é um papel real (`users.role = assistant`): convite pela escola, painel da escola sem Permissões e Assinatura B2B (no front e no back: 403), acesso a alunos, turmas, relatórios, desafios e lições. |
| Outros | **Feito** | `GET /onboarding/steps`; `School*` deixou de chamar `/admin/exchange-rates` e `/admin/regional-prices` (403 para a escola). |

**Verificado no browser (BD sqlite local):** painel do professor com turma, 3 alunos e fila real → "Aprovar" pelo painel (BD: `approved`, 50 XP, 50 moedas, avaliador gravado) → relatórios da escola mostram 100 XP, 100 % conclusão, Prof Teste com 1 avaliação; checkout B2B pela interface cria a fatura `pending` (450 000 Kz) → webhook assinado → "Subscrição B2B ativa" e relatórios avançados desbloqueados.

**Testes:** `SchoolModulesTest` (5 testes); suite do back: 52 passam, 7 antigos falham (factories em falta).

**Ainda em aberto:** *Prémios da escola* (`SchoolRewards`: prémios ganhos em desafios patrocinados) não têm modelo na API, a página mostra estado vazio; convites de professores a escolas (`/invitations/*`) não existem (o convite cria a conta); o preço da assinatura vem da tabela `plans` (a escola vê "School Premium" semeado); as permissões `can_edit_classes` e `can_view_reports` ainda não são aplicadas nos endpoints (só gravadas e usadas pela UI); `use-badges` (`/badges`, `/leaderboard/household`) continua sem rota; relatórios avançados exigem subscrição ativa (403 sem ela, por desenho).

## 19. Parceiro — módulos restantes (2026-11-03)

O `PartnerImpactService` era **100 % inventado** (alunos = `desafios × 45`, escolas = `programas × 2`, "32 missões por desafio", índice de impacto 8,5, conclusão 0,82/84,5/88,5 %), a série temporal do relatório era fixa (Mar–Ago 2026) e o CSV de exportação sorteava alunos (`mt_rand`) e punha sempre "Luanda". As páginas Relatórios, Prémios e Subscrição mostravam dados fictícios.

| Módulo | Estado | O que foi feito |
|---|---|---|
| Relatórios / Painel | **Feito (grave)** | Métricas reais por turmas inscritas nos desafios do parceiro: alunos, escolas, conclusão, missões, índice de impacto = conclusão/10, repartição concluído/em progresso/para rever; série dos últimos 6 meses (alunos, missões, XP) e distribuição por **província da escola** (`GET /partner/reports/chart-data` → `time_series`, `geo`); CSV com dados reais; tudo a zero sem atividade. Front: `PartnerReports` e `PartnerDashboard` sem constantes (1 480 alunos, 22 escolas, 150 000 Kz de saldo, "+24 %", "5 Províncias", "92,6 %"). |
| Prémios | **Feito (novo)** | Não havia modelo. Nova tabela `challenge_prizes`: `POST/GET/PATCH/DELETE /partner/prizes` (só turmas inscritas no desafio; marcar entregue grava a data; entregue não se elimina; isolamento por parceiro) e `GET /school/prizes` (a escola vê os seus). `PartnerRewards` e `SchoolRewards` passam a usar a API (registar prémio, marcar como entregue). |
| Convites de programa | **Feito (novo)** | Tabela `program_invitations`: `POST /partner/programs/{id}/invite` (código de 8 letras, familiar ou escola, validade; 409 se já há pendente), `GET /partner/programs/{id}/invitations`, `GET /partner/invitations`, `DELETE /partner/invitations/{id}` (revoga pendentes); lado do destinatário: `GET /invitations/mine`, `GET /partner/invitations/{code}/validate`, `POST /partner/invitations/accept\|reject` (encarregado só aceita convites de famílias, coordenador só de escolas). |
| Subscrição | **Feito (grave)** | Página simulava pagamento (confetti, "ativa até 15 de Outubro de 2027"), preços fixos (150 000/15 000 Kz) e faturas inventadas. Agora: catálogo de planos de parceiro, `POST /partner/subscription/subscribe` **debita a carteira KVC do parceiro** (1 Kz = 10 KVC; saldo insuficiente → 422 e nada muda), faturas reais (`partner_invoices`), validade real, cancelar. |
| Perfil | **Feito** | `GET/PATCH /partner/profile` + `POST /partner/profile/logo` (NIF, setor, província, representante…); a conta (nome/avatar/idioma) por `PATCH /auth/me`. Antes chamava `/profiles/{id}` e `/tenants/{id}` (parceiros não têm tenant). |

**Verificado no browser (BD sqlite local):** painel do parceiro com 1 aluno, 1 escola (Luanda), 100 000 KVC distribuídos, conclusão 100 % e carteira de 300 000 Kz → registar prémio pela interface (turma inscrita no desafio) → marcar como entregue (BD: `delivered`, data, `tenant_id` da escola) → subscrição anual pela interface: carteira 3 000 000 → 1 500 000 KVC, fatura `INV-…` paga, plano até 01/11/2027.

**Testes:** `PartnerModulesTest` (4 testes, 77 asserções); suite do back: 56 passam, 7 antigos falham (factories em falta).

**Ainda em aberto:** `/partner/limits` continua a usar `config/partners.php` (não os limites do plano subscrito); `PartnerWallet` gera recibos PDF a partir do extrato local (sem faturas de carregamento na API); criar uma escola ("Nova Escola — Vincular polo") no painel não tem endpoint; o prémio não tem upload de comprovativo (só URL via API); aceitar um convite apenas regista o vínculo (ainda não inscreve automaticamente turmas/famílias nos desafios do programa).
