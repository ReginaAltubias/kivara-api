<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AccountProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTenantController extends Controller
{
    private const TYPES = ['family', 'school', 'institutional_partner', 'partner'];

    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query()->withCount('users');

        if ($type = $request->query('type', $request->query('tenant_type'))) {
            $query->where('tenant_type', $type);
        }
        $status = $request->query('status');
        if ($status !== null && $status !== '') {
            $query->where('is_active', in_array($status, ['active', '1', 'true'], true));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $like = '%' . mb_strtolower($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(nif) LIKE ?', [$like]);
            });
        }

        $tenants = $query->orderBy('name')->get()->map(fn (Tenant $t) => $this->present($t));

        return response()->json(['status' => 'success', 'data' => $tenants]);
    }

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::withCount('users')->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $this->present($tenant)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $data['tenant_type'] = $data['tenant_type'] ?? 'school';

        $tenant = Tenant::create($this->toColumns($data));

        $emailSent = null;
        $adminEmail = $data['contact_email'] ?? $data['admin_email'] ?? null;
        if ($adminEmail && $data['tenant_type'] === 'school' && !User::where('email', $adminEmail)->exists()) {
            $result = AccountProvisioner::create([
                'tenant_id' => $tenant->id,
                'name' => $data['director_name'] ?? $tenant->name,
                'email' => $adminEmail,
                'role' => 'coordinator',
            ]);
            $tenant->update(['admin_email' => $adminEmail]);
            $emailSent = $result['email_sent'];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Tenant criado com sucesso.',
            'email_sent' => $emailSent,
            'data' => $this->present($tenant->fresh()->loadCount('users')),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $data = $request->validate($this->rules($tenant, partial: true));

        $tenant->update($this->toColumns($data));

        return response()->json([
            'status' => 'success',
            'message' => 'Tenant atualizado com sucesso.',
            'data' => $this->present($tenant->fresh()->loadCount('users')),
        ]);
    }

    /** Elimina o tenant apenas se nao tiver utilizadores; caso contrario pede desativacao. */
    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::withCount('users')->findOrFail($id);

        if ($tenant->users_count > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'O tenant tem utilizadores associados. Desative-o em vez de o eliminar.',
            ], 409);
        }

        $tenant->delete();

        return response()->json(['status' => 'success', 'message' => 'Tenant eliminado.']);
    }

    public function activate(string $id): JsonResponse
    {
        return $this->setActive($id, true);
    }

    public function deactivate(string $id): JsonResponse
    {
        return $this->setActive($id, false);
    }

    public function subscription(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $data = $request->validate([
            'subscription_tier_id' => ['nullable', 'uuid', Rule::exists((new SubscriptionPlan)->getTable(), 'id')],
        ]);

        $tenant->update(['subscription_tier_id' => $data['subscription_tier_id'] ?? null]);

        return response()->json(['status' => 'success', 'data' => $this->present($tenant->fresh())]);
    }

    public function agreement(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['agreement' => $request->all()]);

        return response()->json(['status' => 'success', 'message' => 'Acordo guardado.', 'data' => $tenant->agreement]);
    }

    /** Gera novas credenciais para o administrador da escola e reenvia o email. */
    public function sendCredentials(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $email = $tenant->admin_email ?? $tenant->email;

        $admin = $email ? User::where('email', $email)->first() : null;
        $admin ??= User::where('tenant_id', $tenant->id)->whereIn('role', ['coordinator', 'coordenador'])->first();

        if (!$admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este tenant nao tem administrador. Atribua um com reassign-admin.',
            ], 404);
        }

        $sent = AccountProvisioner::reissue($admin);

        return response()->json([
            'status' => $sent ? 'success' : 'error',
            'message' => $sent ? "Credenciais enviadas para {$admin->email}." : 'Credenciais geradas, mas o email nao pode ser enviado.',
            'email_sent' => $sent,
        ], $sent ? 200 : 502);
    }

    public function reassignAdmin(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $data = $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $data['email'])->first();
        $emailSent = null;

        if ($user && $user->tenant_id && $user->tenant_id !== $tenant->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este email pertence a um utilizador de outro tenant.',
            ], 422);
        }

        if ($user) {
            $user->update(['tenant_id' => $tenant->id, 'role' => 'coordinator']);
            AccountProvisioner::syncRole($user, 'coordinator');
        } else {
            $result = AccountProvisioner::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'] ?? $tenant->director_name ?? $tenant->name,
                'email' => $data['email'],
                'role' => 'coordinator',
            ]);
            $emailSent = $result['email_sent'];
        }

        $tenant->update(['admin_email' => $data['email']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Administrador atribuido.',
            'email_sent' => $emailSent,
            'data' => $this->present($tenant->fresh()),
        ]);
    }

    /** Utilizadores do tenant; filtra por papel com ?role=teacher. */
    public function members(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $query = User::with('roles:id,name')->where('tenant_id', $tenant->id);

        if ($role = $request->query('role')) {
            $query->where(function ($q) use ($role) {
                $q->where('role', $role)->orWhereHas('roles', fn ($r) => $r->where('name', $role));
            });
        }

        $members = $query->orderBy('name')->get()->map(function (User $u) {
            $roles = $u->roles->pluck('name')->push($u->role)->filter()->unique()->values();

            return [
                'id' => $u->id,
                'display_name' => $u->name,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'roles' => $roles,
                'is_active' => (bool) $u->is_active,
                'user' => ['email' => $u->email, 'roles' => $roles],
            ];
        });

        return response()->json(['status' => 'success', 'data' => $members]);
    }

    public function classrooms(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $rooms = Classroom::where('tenant_id', $tenant->id)
            ->withCount('students as student_count')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'grade_level' => $c->grade_level,
                'grade' => $c->grade_level,
                'year' => $c->year,
                'academic_year' => $c->year,
                'student_count' => $c->student_count,
            ]);

        return response()->json(['status' => 'success', 'data' => $rooms]);
    }

    public function students(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $students = User::where('tenant_id', $tenant->id)
            ->whereIn('role', ['teen', 'yang'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'student_profile_id' => $u->id,
                'profile' => [
                    'id' => $u->id,
                    'display_name' => $u->name,
                    'user' => ['email' => $u->email],
                    'is_active' => (bool) $u->is_active,
                ],
                'guardian' => null,
            ]);

        return response()->json(['status' => 'success', 'data' => $students]);
    }

    private function setActive(string $id, bool $active): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['is_active' => $active]);

        return response()->json([
            'status' => 'success',
            'message' => $active ? 'Tenant ativado.' : 'Tenant desativado.',
            'data' => $this->present($tenant->fresh()),
        ]);
    }

    private function rules(?Tenant $tenant = null, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'max:255', Rule::unique('tenants', 'name')->ignore($tenant?->id)],
            'tenant_type' => ['sometimes', Rule::in(self::TYPES)],
            'currency' => 'sometimes|string|size:3',
            'is_active' => 'sometimes|boolean',
            'nif' => 'nullable|string|max:30',
            'school_type' => 'nullable|string|max:50',
            'education_level' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'contact_email' => 'nullable|email',
            'admin_email' => 'nullable|email',
            'website' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'domain' => ['nullable', 'string', 'max:255', Rule::unique('tenants', 'domain')->ignore($tenant?->id)],
            'director_name' => 'nullable|string|max:255',
            'director_phone' => 'nullable|string|max:30',
            'director_email' => 'nullable|email',
            'subscription_tier_id' => ['nullable', 'uuid', Rule::exists((new SubscriptionPlan)->getTable(), 'id')],
        ];
    }

    /** Mapeia `contact_email` para a coluna `email`. */
    private function toColumns(array $data): array
    {
        if (array_key_exists('contact_email', $data)) {
            $data['email'] = $data['contact_email'];
        }
        unset($data['contact_email']);

        return $data;
    }

    private function present(Tenant $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'tenant_type' => $t->tenant_type,
            'type' => $t->tenant_type,
            'currency' => $t->currency,
            'is_active' => (bool) $t->is_active,
            'status' => $t->is_active ? 'active' : 'inactive',
            'nif' => $t->nif,
            'school_type' => $t->school_type,
            'education_level' => $t->education_level,
            'country' => $t->country,
            'province' => $t->province,
            'city' => $t->city,
            'address' => $t->address,
            'phone' => $t->phone,
            'contact_email' => $t->email,
            'admin_email' => $t->admin_email,
            'website' => $t->website,
            'description' => $t->description,
            'domain' => $t->domain,
            'director_name' => $t->director_name,
            'director_phone' => $t->director_phone,
            'director_email' => $t->director_email,
            'logo_url' => $t->logo_url,
            'agreement' => $t->agreement,
            'subscription_tier_id' => $t->subscription_tier_id,
            'user_count' => $t->users_count ?? null,
            'created_at' => $t->created_at?->toISOString(),
        ];
    }
}
