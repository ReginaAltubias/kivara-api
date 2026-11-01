<?php

namespace App\Http\Controllers\Api\ParentGate;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\DreamVault;
use App\Models\Family;
use App\Models\LedgerEntry;
use App\Models\Mission;
use App\Models\Streak;
use App\Models\User;
use App\Models\Wallet;
use App\Support\AccountProvisioner;
use App\Support\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Agregados da familia do encarregado: carteiras (em KVC), movimentos, emissao,
 * guardioes e resumo semanal.
 */
class FamilyController extends Controller
{
    // ------------------------------------------------------------------ carteiras (KVC)

    /** GET /wallets — carteiras do utilizador e dos seus filhos, em KVC. */
    public function wallets(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallets = Wallet::query()->ownedBy($user)->get();

        return response()->json(['status' => 'success', 'data' => $wallets->map(fn (Wallet $w) => [
            'id' => $w->id,
            'profile_id' => $w->walletable_id,
            'wallet_type' => 'virtual',
            'currency' => 'KVC',
            'balance' => $this->kvc($w->balance),
            'is_frozen' => (bool) $w->is_frozen,
        ])->values()]);
    }

    public function walletBalance(Request $request, string $id): JsonResponse
    {
        $wallet = Wallet::query()->ownedBy($request->user())->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => [
            'wallet_id' => $wallet->id,
            'currency' => 'KVC',
            'balance' => $this->kvc($wallet->balance),
        ]]);
    }

    public function walletTransactions(Request $request, string $id): JsonResponse
    {
        $wallet = Wallet::query()->ownedBy($request->user())->findOrFail($id);
        $limit = min(max((int) $request->query('limit', 20), 1), 200);

        $rows = LedgerEntry::where('wallet_id', $wallet->id)->orderByDesc('created_at')->limit($limit)->get();

        return response()->json(['status' => 'success', 'data' => $rows->map(fn (LedgerEntry $e) => [
            'id' => $e->id,
            'amount' => $this->kvc($e->amount),
            'entry_type' => $e->entry_type,
            'description' => $e->description,
            'direction' => $e->direction,
            'metadata' => $e->metadata ?? [],
            'created_at' => $e->created_at?->toISOString(),
        ])->values()]);
    }

    // ------------------------------------------------------------------ agregados

    /** GET /households/{id}/transactions */
    public function transactions(Request $request, string $id): JsonResponse
    {
        $this->authorizeHousehold($request, $id);
        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);

        [$wallets, $owners] = $this->householdWallets($id);

        $page = LedgerEntry::whereIn('wallet_id', $wallets->keys())->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => collect($page->items())->map(function (LedgerEntry $e) use ($wallets, $owners) {
                $ownerId = $wallets[$e->wallet_id];
                $owner = $owners[$ownerId] ?? ['name' => 'Criança', 'avatar' => '👧'];

                return [
                    'id' => $e->id,
                    'amount' => $this->kvc($e->amount),
                    'description' => $e->description,
                    'entry_type' => $e->entry_type,
                    'direction' => $e->direction,
                    'created_at' => $e->created_at?->toISOString(),
                    'profile_id' => $ownerId,
                    'display_name' => $owner['name'],
                    'avatar' => $owner['avatar'],
                ];
            })->values(),
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    /** GET /stats/emission — KVC emitidos (missoes e tarefas) pela familia no mes. */
    public function emission(Request $request): JsonResponse
    {
        $user = $request->user();
        $monthStart = now()->startOfMonth();

        $childWallets = Wallet::query()->ownedBy($user)->where('walletable_type', ChildProfile::class)->pluck('id');
        $emittedAoa = (float) LedgerEntry::whereIn('wallet_id', $childWallets)
            ->where('direction', 'credit')->whereIn('entry_type', ['mission_reward', 'task_reward'])
            ->where('created_at', '>=', $monthStart)->sum('amount');

        $emitted = (int) round($emittedAoa * WalletLedger::KVC_PER_AOA);
        $limit = WalletLedger::emissionLimitKvc();

        return response()->json(['status' => 'success', 'data' => ['emission_stats' => [
            'emitted_this_month' => $emitted,
            'emission_limit' => $limit,
            'remaining' => max($limit - $emitted, 0),
            'percentage_used' => $limit > 0 ? round(min($emitted / $limit, 1) * 100, 1) : 0,
            'month_start' => $monthStart->toDateString(),
            'has_override' => false,
        ]]]);
    }

    /** POST /weekly-summary {householdId} */
    public function weeklySummary(Request $request): JsonResponse
    {
        $id = (string) ($request->input('householdId') ?? $request->user()->family_id);
        $this->authorizeHousehold($request, $id);
        $since = now()->subDays(7);

        $children = ChildProfile::where('family_id', $id)->get();
        $summaries = $children->map(function (ChildProfile $c) use ($since) {
            $walletIds = Wallet::where('walletable_type', ChildProfile::class)->where('walletable_id', $c->id)->pluck('id');
            $week = LedgerEntry::whereIn('wallet_id', $walletIds)->where('created_at', '>=', $since);
            $credits = (float) (clone $week)->where('direction', 'credit')->sum('amount');
            $debits = (float) (clone $week)->where('direction', 'debit')->sum('amount');
            $tasks = (clone $week)->where('entry_type', 'task_reward')->where('direction', 'credit');
            $missionEntries = (clone $week)->where('entry_type', 'mission_reward')->where('direction', 'credit');
            $streak = Streak::where('child_profile_id', $c->id)->first();

            return [
                'profileId' => $c->id,
                'displayName' => $c->name,
                'avatar' => $c->avatar ?: '👧',
                'balance' => $this->kvc(Wallet::whereIn('id', $walletIds)->sum('balance')),
                'weeklyNetChange' => $this->kvc($credits - $debits),
                'tasksCompleted' => (clone $tasks)->count(),
                'tasksEarned' => $this->kvc((clone $tasks)->sum('amount')),
                'missionsCompleted' => Mission::where('child_id', $c->id)->where('completed_at', '>=', $since)->count(),
                'missionsActive' => Mission::where('child_id', $c->id)->whereIn('status', ['available', 'active', 'in_progress'])->count(),
                'missionsEarned' => $this->kvc($missionEntries->sum('amount')),
                'totalSaved' => $this->kvc(DreamVault::where('child_profile_id', $c->id)->sum('current_amount')),
                'currentStreak' => (int) ($streak?->current_streak ?? 0),
                'longestStreak' => (int) ($streak?->best_streak ?? 0),
            ];
        })->values();

        return response()->json(['status' => 'success', 'children' => $summaries]);
    }

    // ------------------------------------------------------------------ familia / guardioes

    /** POST /households — devolve (ou cria) a familia do utilizador. */
    public function storeHousehold(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->family_id) {
            $family = Family::create(['tenant_id' => $user->tenant_id, 'name' => $user->name, 'currency' => 'AOA']);
            $user->forceFill(['family_id' => $family->id])->save();
        }

        return response()->json(['status' => 'success', 'id' => $user->family_id, 'data' => ['id' => $user->family_id]], 201);
    }

    public function guardians(Request $request, string $id): JsonResponse
    {
        $this->authorizeHousehold($request, $id);
        $users = User::where('family_id', $id)->where('is_active', true)->orderBy('created_at')->get();
        $primaryId = $users->first()?->id;

        return response()->json(['status' => 'success', 'data' => $users->map(fn (User $u) => [
            'id' => $u->id,
            'profile_id' => $u->id,
            'role' => $u->id === $primaryId ? 'primary' : 'secondary',
            'display_name' => $u->name,
            'avatar' => $u->avatar ?: '👤',
            'created_at' => $u->created_at?->toISOString(),
        ])->values()]);
    }

    /**
     * POST /households/{id}/invite
     *  - {email}: convida um segundo encarregado (conta criada com credenciais por e-mail);
     *  - {code}: guarda o codigo de convite da familia.
     */
    public function invite(Request $request, string $id): JsonResponse
    {
        $this->authorizeHousehold($request, $id);
        $user = $request->user();

        if ($request->filled('code') && ! $request->filled('email')) {
            $request->validate(['code' => 'required|string|max:40']);
            Family::whereKey($id)->update(['invite_code' => $request->string('code')]);

            return response()->json(['status' => 'success', 'message' => 'Código de convite guardado.']);
        }

        $data = $request->validate(['email' => 'required|email|max:255|unique:users,email']);

        if (! $this->isPrimaryGuardian($user, $id) && $user->role !== 'admin') {
            return response()->json(['status' => 'error', 'message' => 'Apenas o encarregado principal pode convidar.'], 403);
        }

        $result = AccountProvisioner::create([
            'name' => ucfirst(explode('@', $data['email'])[0]),
            'email' => $data['email'],
            'role' => 'parent',
            'tenant_id' => $user->tenant_id,
            'family_id' => $id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $result['email_sent'] ? 'Convite enviado por e-mail.' : 'Conta criada, mas o e-mail não pôde ser enviado.',
            'data' => ['id' => $result['user']->id, 'email_sent' => $result['email_sent']],
        ], 201);
    }

    public function removeGuardian(Request $request, string $id, string $guardianId): JsonResponse
    {
        $this->authorizeHousehold($request, $id);
        $user = $request->user();

        if (! $this->isPrimaryGuardian($user, $id) && $user->role !== 'admin') {
            return response()->json(['status' => 'error', 'message' => 'Apenas o encarregado principal pode remover.'], 403);
        }

        $guardian = User::where('family_id', $id)->findOrFail($guardianId);
        if ($this->isPrimaryGuardian($guardian, $id)) {
            return response()->json(['status' => 'error', 'message' => 'O encarregado principal não pode ser removido.'], 422);
        }

        $guardian->tokens()->delete();
        $guardian->forceFill(['family_id' => null, 'tenant_id' => null, 'is_active' => false])->save();

        return response()->json(['status' => 'success', 'message' => 'Encarregado removido.']);
    }

    // ------------------------------------------------------------------ helpers

    private function kvc($aoa): float
    {
        return round((float) $aoa * WalletLedger::KVC_PER_AOA, 2);
    }

    private function authorizeHousehold(Request $request, string $id): void
    {
        $user = $request->user();
        abort_unless($user->role === 'admin' || ($user->family_id && $user->family_id === $id), 404);
    }

    private function isPrimaryGuardian(User $user, string $familyId): bool
    {
        return User::where('family_id', $familyId)->orderBy('created_at')->value('id') === $user->id;
    }

    /** @return array{0: \Illuminate\Support\Collection<string,string>, 1: array<string,array{name:string,avatar:string}>} */
    private function householdWallets(string $familyId): array
    {
        $users = User::where('family_id', $familyId)->get(['id', 'name', 'avatar']);
        $children = ChildProfile::where('family_id', $familyId)->get(['id', 'name', 'avatar']);

        $owners = [];
        foreach ($users as $u) {
            $owners[$u->id] = ['name' => $u->name, 'avatar' => $u->avatar ?: '👤'];
        }
        foreach ($children as $c) {
            $owners[$c->id] = ['name' => $c->name, 'avatar' => $c->avatar ?: '👧'];
        }

        $wallets = Wallet::query()
            ->where(fn ($q) => $q->where(fn ($x) => $x->where('walletable_type', User::class)->whereIn('walletable_id', $users->pluck('id')))
                ->orWhere(fn ($x) => $x->where('walletable_type', ChildProfile::class)->whereIn('walletable_id', $children->pluck('id'))))
            ->pluck('walletable_id', 'id');

        return [$wallets, $owners];
    }
}
