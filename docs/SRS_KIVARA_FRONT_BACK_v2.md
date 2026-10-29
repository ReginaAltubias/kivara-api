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
