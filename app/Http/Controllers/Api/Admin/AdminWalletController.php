<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Wallet::with(['tenant:id,name', 'walletable']);

        $frozen = $request->query('is_frozen');
        if ($frozen !== null && $frozen !== '') {
            $query->where('is_frozen', filter_var($frozen, FILTER_VALIDATE_BOOLEAN));
        }
        if ($tenantId = $request->query('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        $wallets = $query->latest()->limit(200)->get()->map(fn (Wallet $w) => $this->present($w));

        return response()->json(['status' => 'success', 'data' => $wallets]);
    }

    public function freeze(Request $request, string $id): JsonResponse
    {
        $wallet = Wallet::findOrFail($id);
        $data = $request->validate(['reason' => 'required|string|max:500']);

        $wallet->update([
            'is_frozen' => true,
            'freeze_reason' => $data['reason'],
            'frozen_at' => now(),
            'frozen_by' => $request->user()->id,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Carteira congelada.', 'data' => $this->present($wallet->fresh(['tenant:id,name', 'walletable']))]);
    }

    public function unfreeze(string $id): JsonResponse
    {
        $wallet = Wallet::findOrFail($id);
        $wallet->update(['is_frozen' => false, 'freeze_reason' => null, 'frozen_at' => null, 'frozen_by' => null]);

        return response()->json(['status' => 'success', 'message' => 'Carteira descongelada.', 'data' => $this->present($wallet->fresh(['tenant:id,name', 'walletable']))]);
    }

    /** Resolve nomes de utilizadores e perfis de crianca por id: ?ids=a,b,c */
    public function profiles(Request $request): JsonResponse
    {
        $ids = array_filter(explode(',', (string) $request->query('ids', '')));

        $users = User::whereIn('id', $ids)->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'display_name' => $u->name]);
        $children = ChildProfile::whereIn('id', $ids)->get()->map(fn ($c) => ['id' => $c->id, 'display_name' => $c->display_name ?? $c->name ?? $c->id]);

        return response()->json(['status' => 'success', 'data' => $users->concat($children)->values()]);
    }

    private function present(Wallet $w): array
    {
        $owner = $w->walletable;
        $ownerName = $owner?->display_name ?? $owner?->name ?? $w->walletable_id;

        return [
            'id' => $w->id,
            'tenant_id' => $w->tenant_id,
            'tenant_name' => $w->tenant?->name,
            'profile_id' => $w->walletable_id,
            'owner_name' => $ownerName,
            'balance' => (float) $w->balance,
            'currency' => $w->currency,
            'is_frozen' => (bool) $w->is_frozen,
            'freeze_reason' => $w->freeze_reason,
            'frozen_at' => $w->frozen_at?->toISOString(),
            'frozen_by' => $w->frozen_by,
        ];
    }
}
