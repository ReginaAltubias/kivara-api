<?php

namespace App\Http\Controllers\Api\User;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\Tenant;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChildProfileController extends Controller
{
    /**
     * Lista os perfis de filhos do encarregado autenticado.
     */
        #[OA\Get(
        path: "/api/v1/children",
        summary: "Children (GET)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $children = ChildProfile::query()
            ->where('parent_id', $user->id)
            ->with(['wallets', 'tasks', 'dreamVaults', 'allowanceConfig', 'classrooms'])
            ->orderBy('name')
            ->get();

        $formatted = $children->map(fn (ChildProfile $child) => $this->transformChild($child));

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
        ], 200);
    }

    /**
     * Regista um novo perfil de filho e aprovisiona as suas carteiras.
     */
        #[OA\Post(
        path: "/api/v1/children",
        summary: "Children (POST)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        // Limite de criancas do plano (mais as vagas extra compradas); sem plano nao ha limite.
        $owner = $request->user();
        if ($owner->role === 'parent' && $owner->tenant_id) {
            $tenant = \App\Models\Tenant::find($owner->tenant_id);
            $max = $tenant?->subscription_tier_id ? \App\Models\SubscriptionPlan::find($tenant->subscription_tier_id)?->max_children : null;
            if ($max !== null && ChildProfile::where('parent_id', $owner->id)->count() >= $max + (int) $tenant->extra_children) {
                return response()->json(['status' => 'error', 'code' => 'child_limit_reached', 'message' => 'Atingiu o limite de crianças do seu plano. Adicione uma criança extra ou mude de plano.'], 422);
            }
        }

        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'nickname' => 'nullable|string|max:100',
            'username' => 'nullable|string|max:100|unique:child_profiles,username',
            'auth_method' => ['required', Rule::in(['pin', 'email_password'])],
            'pin' => 'nullable|required_if:auth_method,pin|string|min:4|max:6',
            'email' => 'nullable|required_if:auth_method,email_password|email|max:255|unique:child_profiles,email|unique:users,email',
            'password' => 'nullable|required_if:auth_method,email_password|string|min:6',
            'date_of_birth' => 'nullable|date|before:today',
            'avatar' => 'nullable|string|max:255',
            'product_phase' => 'nullable|integer|in:1,2,3',
            'dashboard_role' => 'nullable|string|in:child,teen,jovem',
            'school_tenant_id' => 'nullable|uuid|exists:tenants,id',
            'monthly_budget' => 'nullable|numeric|min:0',
            'daily_spend_limit' => 'nullable|numeric|min:0',
        ]);

        $user = $request->user();

        if (!empty($validated['school_tenant_id']) && $user->role !== 'admin') {
            abort(422, 'A associação a uma escola faz-se pelo código de convite da turma.');
        }

        $tenantId = $validated['school_tenant_id'] 
            ?? $user->tenant_id 
            ?? Tenant::first()?->id;

        if (!$tenantId) {
            $defaultTenant = Tenant::firstOrCreate(
                ['slug' => 'kivara-default'],
                ['name' => 'KIVARA Global', 'email' => 'contact@kivara.io']
            );
            $tenantId = $defaultTenant->id;
        }

        $child = DB::transaction(function () use ($validated, $user, $tenantId) {
            $username = $validated['username'] ?? ('kiva_' . strtolower(Str::slug($validated['display_name'])) . '_' . Str::lower(Str::random(4)));
            
            // Garantir unicidade do username
            while (ChildProfile::where('username', $username)->exists()) {
                $username = 'kiva_' . strtolower(Str::slug($validated['display_name'])) . '_' . Str::lower(Str::random(5));
            }

            $productPhase = $validated['product_phase'] ?? ($validated['auth_method'] === 'pin' ? 1 : 2);
            $dashboardRole = $validated['dashboard_role'] ?? ($productPhase === 1 ? 'child' : 'teen');

            $child = ChildProfile::create([
                'parent_id' => $user->id,
                'family_id' => $user->family_id,
                'tenant_id' => $tenantId,
                'name' => $validated['display_name'],
                'nickname' => $validated['nickname'] ?? null,
                'username' => $username,
                'pin' => !empty($validated['pin']) ? Hash::make($validated['pin']) : null,
                'email' => $validated['email'] ?? null,
                'password' => !empty($validated['password']) ? Hash::make($validated['password']) : null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'avatar' => $validated['avatar'] ?? '🦁',
                'auth_method' => $validated['auth_method'],
                'product_phase' => $productPhase,
                'dashboard_role' => $dashboardRole,
                'monthly_budget' => $validated['monthly_budget'] ?? 0,
                'daily_spend_limit' => $validated['daily_spend_limit'] ?? 0,
                'total_xp' => 0,
            ]);

            // Provisionamento da Carteira Polimórfica (KVC)
            Wallet::firstOrCreate(
                [
                    'walletable_id' => $child->id,
                    'walletable_type' => ChildProfile::class,
                    'tenant_id' => $tenantId,
                ],
                [
                    'currency' => 'KVC',
                    'balance' => 0,
                    'is_frozen' => false,
                ]
            );

            return $child;
        });

        $child->load(['wallets', 'tasks', 'dreamVaults', 'allowanceConfig']);

        return response()->json([
            'status' => 'success',
            'message' => 'Perfil de filho criado com sucesso.',
            'data' => $this->transformChild($child),
        ], 201);
    }

    /**
     * Detalhes de um perfil de filho específico.
     */
        #[OA\Get(
        path: "/api/v1/children/{id}",
        summary: "Children {id} (GET)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $child = ChildProfile::where('parent_id', $user->id)
            ->with(['wallets', 'tasks', 'dreamVaults', 'allowanceConfig', 'classrooms'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->transformChild($child),
        ], 200);
    }

    /**
     * Atualiza dados pessoais e limites do filho.
     */
        #[OA\Patch(
        path: "/api/v1/children/{id}",
        summary: "Children {id} (PATCH)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $child = ChildProfile::where('parent_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'display_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'avatar' => 'nullable|string|max:255',
            'monthly_budget' => 'nullable|numeric|min:0',
            'daily_spend_limit' => 'nullable|numeric|min:0',
            'school_tenant_id' => 'nullable|uuid|exists:tenants,id',
            'tenant_id' => 'nullable|uuid|exists:tenants,id',
            'product_phase' => 'nullable|integer|in:1,2,3',
            'dashboard_role' => 'nullable|string|in:child,teen,jovem',
        ]);

        if (isset($validated['display_name'])) {
            $validated['name'] = $validated['display_name'];
        }

        if (isset($validated['school_tenant_id'])) {
            $validated['tenant_id'] = $validated['school_tenant_id'];
        }

        $child->update($validated);
        $child->load(['wallets', 'tasks', 'dreamVaults', 'allowanceConfig']);

        return response()->json([
            'status' => 'success',
            'message' => 'Perfil atualizado com sucesso.',
            'data' => $this->transformChild($child),
        ], 200);
    }

    /**
     * Resumo pedagógico e de engajamento do filho (GET /children/{id}/summary).
     */
        #[OA\Get(
        path: "/api/v1/parent/children/{id}/summary",
        summary: "Parent Children {id} Summary (GET)",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function summary(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $child = ChildProfile::query()
            ->when($user->role !== 'admin', fn ($q) => $q->where('parent_id', $user->id))
            ->findOrFail($id);

        $progress = \App\Models\UserProgress::where('child_profile_id', $child->id);
        $missions = \App\Models\Mission::where('child_id', $child->id);

        return response()->json([
            'data' => [
                'lessons_completed' => (clone $progress)->where('completed', true)->count(),
                'lessons_total' => \App\Models\Lesson::count(),
                'missions_completed' => (clone $missions)->whereIn('status', ['completed', 'approved', 'validada'])->count(),
                'missions_in_progress' => (clone $missions)->whereIn('status', ['active', 'in_progress', 'pending'])->count(),
                'missions_overdue' => (clone $missions)->whereIn('status', ['active', 'in_progress', 'pending'])
                    ->whereNotNull('deadline')->where('deadline', '<', now())->count(),
                'kiva_points' => (int) ($child->total_xp ?? 0),
                'streak_days' => (int) (\App\Models\Streak::where('child_profile_id', $child->id)->value('current_streak') ?? 0),
            ],
        ], 200);
    }

    /**
     * Elimina um perfil de filho.
     */
        #[OA\Delete(
        path: "/api/v1/children/{id}",
        summary: "Children {id} (DELETE)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 204, description: "Recurso removido com sucesso.")
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $child = ChildProfile::where('parent_id', $user->id)->findOrFail($id);

        DB::transaction(function () use ($child) {
            // Eliminar carteiras associadas
            $child->wallets()->delete();
            $child->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Perfil eliminado com sucesso.',
        ], 200);
    }

    /**
     * Upgrade de Autenticação: Transição de PIN para Email/Password.
     */
        #[OA\Post(
        path: "/api/v1/children/{id}/upgrade-auth",
        summary: "Children {id} Upgrade Auth (POST)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function upgradeAuth(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $child = ChildProfile::where('parent_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'auth_method' => 'required|in:email_password',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('child_profiles', 'email')->ignore($child->id),
                'unique:users,email',
            ],
            'password' => 'required|string|min:6',
        ]);

        $child->update([
            'auth_method' => 'email_password',
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'product_phase' => max(2, $child->product_phase ?? 2),
            'dashboard_role' => $child->dashboard_role === 'child' ? 'teen' : $child->dashboard_role,
        ]);

        $child->load(['wallets', 'tasks', 'dreamVaults', 'allowanceConfig']);

        return response()->json([
            'status' => 'success',
            'message' => 'Autenticação atualizada para e-mail/palavra-passe.',
            'data' => $this->transformChild($child),
        ], 200);
    }

    /**
     * Transforma o modelo ChildProfile no formato compatível com o Frontend (ChildWithBalance).
     */
    private function transformChild(ChildProfile $child): array
    {
        $primaryWallet = $child->wallets->firstWhere('currency', 'KVC') ?? $child->wallet;
        $realWallet = $child->wallets->firstWhere('currency', 'AOA');

        $virtualBalance = $primaryWallet ? (float) $primaryWallet->balance : 0.0;
        $realBalance = $realWallet ? (float) $realWallet->balance : 0.0;

        $walletsList = $child->wallets->map(function ($w) {
            return [
                'id' => $w->id,
                'wallet_type' => $w->currency === 'KVC' ? 'virtual' : 'real',
                'currency' => $w->currency,
                'balance' => (float) $w->balance,
                'is_frozen' => (bool) $w->is_frozen,
            ];
        })->values();

        if ($walletsList->isEmpty() && $primaryWallet) {
            $walletsList = collect([
                [
                    'id' => $primaryWallet->id,
                    'wallet_type' => 'virtual',
                    'currency' => $primaryWallet->currency ?: 'KVC',
                    'balance' => (float) $primaryWallet->balance,
                    'is_frozen' => (bool) $primaryWallet->is_frozen,
                ]
            ]);
        }

        $age = $child->date_of_birth ? (int) $child->date_of_birth->age : null;
        $ageGroup = null;
        if ($age !== null) {
            if ($age <= 10) {
                $ageGroup = '6-10';
            } elseif ($age <= 14) {
                $ageGroup = '11-14';
            } else {
                $ageGroup = '15-18';
            }
        }

        return [
            'id' => $child->id,
            'child_id' => $child->id,
            'profile_id' => $child->id,
            'parent_profile_id' => $child->parent_id,
            'name' => $child->name,
            'display_name' => $child->name,
            'nickname' => $child->nickname,
            'username' => $child->username,
            'avatar' => $child->avatar ?: '🦁',
            'date_of_birth' => $child->date_of_birth?->format('Y-m-d'),
            'age' => $age,
            'age_group' => $ageGroup,
            'total_xp' => (int) ($child->total_xp ?? 0),
            'monthly_budget' => (float) ($child->monthly_budget ?? 0),
            'daily_spend_limit' => (float) ($child->daily_spend_limit ?? 0),
            'school_tenant_id' => $child->tenant_id,
            'tenant_id' => $child->tenant_id,
            'auth_method' => $child->auth_method ?: 'pin',
            'product_phase' => (int) ($child->product_phase ?? 1),
            'dashboard_role' => $child->dashboard_role ?: 'child',
            'email' => $child->email,
            'balance' => $virtualBalance,
            'virtual_balance' => $virtualBalance,
            'real_balance' => $realBalance,
            'real_currency' => 'AOA',
            'wallets' => $walletsList,
            'profile' => [
                'id' => $child->id,
                'display_name' => $child->name,
                'avatar' => $child->avatar ?: '🦁',
                'date_of_birth' => $child->date_of_birth?->format('Y-m-d'),
                'email' => $child->email,
            ],
            'created_at' => $child->created_at?->toISOString(),
            'updated_at' => $child->updated_at?->toISOString(),
        ];
    }
}
