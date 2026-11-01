<?php

namespace App\Http\Controllers\Api\ParentGate;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\Reward;
use App\Models\Wallet;
use App\Support\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pedidos de excecao de orcamento: a crianca pede uma recompensa acima do orcamento
 * e o encarregado aprova (debita a carteira da crianca) ou recusa.
 */
class BudgetExceptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $rows = DB::table('budget_exception_requests')
            ->when($user->role !== 'admin', fn ($q) => $q->where('parent_profile_id', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('reward_id'), fn ($q) => $q->where('reward_id', $request->query('reward_id')))
            ->orderByDesc('created_at')
            ->get();

        $rewards = Reward::whereIn('id', $rows->pluck('reward_id')->filter())->get()->keyBy('id');
        $children = ChildProfile::whereIn('id', $rows->pluck('child_profile_id'))->get()->keyBy('id');
        $data = $rows->map(fn ($r) => $this->present($r, $rewards[$r->reward_id] ?? null, $children[$r->child_profile_id] ?? null))->values();

        // Consulta por recompensa: devolve o pedido (ou null)
        if ($request->filled('reward_id')) {
            return response()->json(['status' => 'success', 'data' => $data->first()]);
        }

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'child_profile_id' => 'required|uuid|exists:child_profiles,id',
            'reward_id' => 'required|uuid|exists:rewards,id',
            'amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:500',
        ]);

        $child = ChildProfile::query()
            ->when($user->role !== 'admin', fn ($q) => $q->where('parent_id', $user->id))
            ->findOrFail($data['child_profile_id']);

        $exists = DB::table('budget_exception_requests')
            ->where('child_profile_id', $child->id)->where('reward_id', $data['reward_id'])->where('status', 'pending')->exists();
        if ($exists) {
            return response()->json(['status' => 'error', 'message' => 'Já existe um pedido pendente para esta recompensa.'], 409);
        }

        $id = (string) Str::uuid();
        DB::table('budget_exception_requests')->insert([
            'id' => $id,
            'child_profile_id' => $child->id,
            'parent_profile_id' => $child->parent_id,
            'reward_id' => $data['reward_id'],
            'amount' => $data['amount'],
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('budget_exception_requests')->find($id);

        return response()->json(['status' => 'success', 'data' => $this->present($row, Reward::find($data['reward_id']), $child)], 201);
    }

    public function resolve(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['action' => 'required|in:approve,reject']);

        $row = DB::table('budget_exception_requests')
            ->when($user->role !== 'admin', fn ($q) => $q->where('parent_profile_id', $user->id))
            ->where('id', $id)->first();
        abort_if(! $row, 404);
        abort_if($row->status !== 'pending', 422, 'O pedido já foi resolvido.');

        $reward = $row->reward_id ? Reward::find($row->reward_id) : null;

        if ($data['action'] === 'approve') {
            $wallet = Wallet::where('walletable_type', ChildProfile::class)->where('walletable_id', $row->child_profile_id)->first();
            abort_if(! $wallet, 422, 'A criança não tem carteira.');

            try {
                WalletLedger::move(
                    $wallet,
                    'debit',
                    (float) $row->amount / WalletLedger::KVC_PER_AOA,
                    'purchase',
                    'Recompensa: ' . ($reward?->title ?? 'excepção de orçamento'),
                    'budget-exception:' . $row->id,
                    ['budget_exception_id' => $row->id, 'reward_id' => $row->reward_id]
                );
            } catch (\DomainException $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
            }
        }

        DB::table('budget_exception_requests')->where('id', $id)->update([
            'status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'resolved_at' => now(),
            'resolved_by' => $user->id,
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'reward_name' => $reward?->title, 'data' => ['id' => $id, 'status' => $data['action'] === 'approve' ? 'approved' : 'rejected']]);
    }

    private function present($r, ?Reward $reward, ?ChildProfile $child): array
    {
        return [
            'id' => $r->id,
            'child_profile_id' => $r->child_profile_id,
            'parent_profile_id' => $r->parent_profile_id,
            'reward_id' => $r->reward_id,
            'amount' => (float) $r->amount,
            'status' => $r->status,
            'reason' => $r->reason,
            'resolved_at' => $r->resolved_at,
            'resolved_by' => $r->resolved_by,
            'created_at' => $r->created_at,
            'reward_name' => $reward?->title,
            'reward_icon' => $reward?->icon,
            'child_name' => $child?->name,
        ];
    }
}
