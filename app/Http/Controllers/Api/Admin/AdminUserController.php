<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AccountProvisioner;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    private const ROLES = ['admin', 'coordinator', 'teacher', 'parent', 'partner', 'teen', 'yang'];

    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with(['tenant:id,name,tenant_type', 'roles:id,name'])->withMax('tokens as last_access_at', 'last_used_at');

        if ($role = $request->query('role')) {
            $query->where(function ($q) use ($role) {
                $q->where('role', $role)->orWhereHas('roles', fn ($r) => $r->where('name', $role));
            });
        }
        if ($tenantId = $request->query('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }
        $status = $request->query('status', $request->query('is_active'));
        if ($status !== null && $status !== '') {
            $query->where('is_active', in_array($status, ['active', '1', 'true', true], true));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $like = '%' . mb_strtolower($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }

        $users = $query->orderBy('name')->get()->map(fn (User $u) => $this->present($u));

        return response()->json(['status' => 'success', 'data' => $users]);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::with(['tenant:id,name,tenant_type', 'roles:id,name'])->withMax('tokens as last_access_at', 'last_used_at')->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $this->present($user)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|unique:users,email',
            'name' => 'required_without:display_name|nullable|string|max:255',
            'display_name' => 'required_without:name|nullable|string|max:255',
            'role' => ['required_without:roles', 'nullable', Rule::in(self::ROLES)],
            'roles' => 'required_without:role|nullable|array|min:1',
            'roles.*' => ['string', Rule::in(self::ROLES)],
            'tenant_id' => ['nullable', 'uuid', Rule::exists((new Tenant)->getTable(), 'id')],
            'password' => 'nullable|string|min:8|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $roles = $data['roles'] ?? [$data['role']];

        $result = AccountProvisioner::create([
            'name' => $data['name'] ?? $data['display_name'],
            'email' => $data['email'],
            'role' => $roles[0],
            'tenant_id' => $data['tenant_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ], $data['password'] ?? null);

        $user = $result['user'];
        if (count($roles) > 1) {
            AccountProvisioner::syncRoles($user, $roles);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Utilizador criado com sucesso.',
            'email_sent' => $result['email_sent'],
            'data' => $this->present($user->load(['tenant:id,name,tenant_type', 'roles:id,name'])),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'display_name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', Rule::in(self::ROLES)],
            'roles' => 'sometimes|array|min:1',
            'roles.*' => ['string', Rule::in(self::ROLES)],
            'tenant_id' => ['sometimes', 'nullable', 'uuid', Rule::exists((new Tenant)->getTable(), 'id')],
            'is_active' => 'sometimes|boolean',
        ]);

        if (($data['is_active'] ?? true) === false && $this->isLastActiveAdmin($user)) {
            return $this->lastAdminError();
        }

        $roles = $data['roles'] ?? (isset($data['role']) ? [$data['role']] : null);
        if ($roles !== null && $this->isLastActiveAdmin($user) && !in_array('admin', $roles, true)) {
            return $this->lastAdminError();
        }

        $fill = array_intersect_key($data, array_flip(['name', 'email', 'tenant_id', 'is_active']));
        if (isset($data['display_name']) && !isset($fill['name'])) {
            $fill['name'] = $data['display_name'];
        }
        if ($roles !== null) {
            $fill['role'] = $roles[0];
        }
        $user->update($fill);

        if ($roles !== null) {
            AccountProvisioner::syncRoles($user, $roles);
        }
        if (($data['is_active'] ?? true) === false) {
            $user->tokens()->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Utilizador atualizado com sucesso.',
            'data' => $this->present($user->fresh(['tenant:id,name,tenant_type', 'roles:id,name'])),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Nao pode eliminar a sua propria conta.'], 422);
        }
        if ($this->isLastActiveAdmin($user)) {
            return $this->lastAdminError();
        }

        try {
            $user->tokens()->delete();
            $user->delete();
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'O utilizador tem dados associados. Desative-o em vez de o eliminar.',
            ], 409);
        }

        return response()->json(['status' => 'success', 'message' => 'Utilizador eliminado.']);
    }

    public function block(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Nao pode bloquear a sua propria conta.'], 422);
        }
        if ($this->isLastActiveAdmin($user)) {
            return $this->lastAdminError();
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return response()->json(['status' => 'success', 'message' => 'Utilizador bloqueado.', 'data' => $this->present($user)]);
    }

    public function unblock(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);

        return response()->json(['status' => 'success', 'message' => 'Utilizador desbloqueado.', 'data' => $this->present($user)]);
    }

    /** Gera novas credenciais e reenvia o email de ativacao. */
    public function resendActivation(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $sent = AccountProvisioner::reissue($user);

        return response()->json([
            'status' => $sent ? 'success' : 'error',
            'message' => $sent ? "Email de ativacao enviado para {$user->email}." : 'Credenciais geradas, mas o email nao pode ser enviado.',
            'email_sent' => $sent,
        ], $sent ? 200 : 502);
    }

    private function isLastActiveAdmin(User $user): bool
    {
        if ($user->role !== 'admin' || !$user->is_active) {
            return false;
        }

        return User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->doesntExist();
    }

    private function lastAdminError(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Operacao recusada: e o ultimo administrador ativo.',
        ], 422);
    }

    private function present(User $u): array
    {
        $roles = $u->roles->pluck('name')->push($u->role)->filter()->unique()->values();

        return [
            'id' => $u->id,
            'user_id' => $u->id,
            'name' => $u->name,
            'display_name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'roles' => $roles,
            'is_active' => (bool) $u->is_active,
            'must_change_password' => (bool) $u->must_change_password,
            'email_verified_at' => $u->email_verified_at?->toISOString(),
            'profile' => [
                'id' => $u->id,
                'user_id' => $u->id,
                'display_name' => $u->name,
                'tenant' => $u->tenant ? ['id' => $u->tenant->id, 'name' => $u->tenant->name, 'tenant_type' => $u->tenant->tenant_type] : null,
                'roles' => $roles,
            ],
            'last_access_at' => $u->getAttribute('last_access_at') ? \Illuminate\Support\Carbon::parse($u->getAttribute('last_access_at'))->toISOString() : null,
            'activation_pending' => $u->activation_code !== null,
            'tenant_id' => $u->tenant_id,
            'tenant' => $u->tenant ? [
                'id' => $u->tenant->id,
                'name' => $u->tenant->name,
                'tenant_type' => $u->tenant->tenant_type,
            ] : null,
            'created_at' => $u->created_at?->toISOString(),
            'updated_at' => $u->updated_at?->toISOString(),
        ];
    }
}
