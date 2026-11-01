<?php

namespace App\Http\Controllers\Api\ParentGate;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\LedgerEntry;
use App\Models\Mission;
use App\Models\MissionTemplate;
use App\Models\Wallet;
use App\Support\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Missoes dos filhos do encarregado: criar, editar, iniciar, concluir (emite KVC) e modelos.
 */
class MissionController extends Controller
{
    /** GET /missions?child_profile_id= — missoes dos filhos do utilizador. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $missions = Mission::query()
            ->with('child:id,name,avatar,parent_id,family_id')
            ->whereHas('child', fn ($c) => $this->visibleChildren($c, $user))
            ->when($request->filled('child_profile_id'), fn ($q) => $q->where('child_id', $request->query('child_profile_id')))
            ->latest()
            ->get();

        return response()->json(['status' => 'success', 'data' => $missions->map(fn (Mission $m) => $this->present($m))->values()], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->teacherCan('can_assign_missions'), 403, 'Sem permissão para atribuir missões.');

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'nullable|string|in:saving,budgeting,planning,custom',
            'target_amount' => 'nullable|numeric|min:0',
            'reward' => 'nullable|numeric|min:0',
            'reward_amount' => 'nullable|numeric|min:0',
            'reward_type' => 'nullable|string|max:50',
            'kiva_points_reward' => 'nullable|integer|min:0',
            'child_profile_id' => 'nullable|uuid|exists:child_profiles,id',
            'child_id' => 'nullable|uuid|exists:child_profiles,id',
            'deadline' => 'nullable|date',
        ]);

        $childId = $data['child_profile_id'] ?? $data['child_id'] ?? null;
        abort_if(! $childId, 422, 'Indique a criança.');
        abort_if(! isset($data['reward']) && ! isset($data['reward_amount']), 422, 'Indique a recompensa.');

        // So se criam missoes para os filhos do encarregado autenticado.
        $this->ownedChild($user, $childId);

        $mission = Mission::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'custom',
            'target_amount' => $data['target_amount'] ?? null,
            'reward_type' => $data['reward_type'] ?? 'kvc',
            'reward_amount' => $data['reward'] ?? $data['reward_amount'],
            'kiva_points_reward' => $data['kiva_points_reward'] ?? 0,
            'child_id' => $childId,
            'deadline' => $data['deadline'] ?? null,
            'status' => 'available',
        ]);

        return response()->json(['status' => 'success', 'data' => $this->present($mission->load('child'))], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $mission = $this->ownedMission($request, $id);
        abort_if($mission->status === 'completed', 422, 'A missão já foi concluída.');

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'type' => 'sometimes|string|in:saving,budgeting,planning,custom',
            'target_amount' => 'sometimes|nullable|numeric|min:0',
            'reward' => 'sometimes|numeric|min:0',
            'kiva_points_reward' => 'sometimes|integer|min:0',
            'deadline' => 'sometimes|nullable|date',
        ]);

        if (isset($data['reward'])) {
            $data['reward_amount'] = $data['reward'];
            unset($data['reward']);
        }

        $mission->update($data);

        return response()->json(['status' => 'success', 'data' => $this->present($mission->fresh('child'))]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->ownedMission($request, $id)->delete();

        return response()->json(['status' => 'success', 'message' => 'Missão eliminada.']);
    }

    public function start(Request $request, string $id): JsonResponse
    {
        $mission = $this->ownedMission($request, $id);
        abort_if($mission->status === 'completed', 422, 'A missão já foi concluída.');

        $mission->update(['status' => 'in_progress', 'started_at' => $mission->started_at ?? now()]);

        return response()->json(['status' => 'success', 'data' => $this->present($mission->fresh('child'))]);
    }

    /** Conclui a missao: emite a recompensa (KVC) para a carteira da crianca, dentro do limite mensal. */
    public function complete(Request $request, string $id): JsonResponse
    {
        $mission = $this->ownedMission($request, $id);

        if ($mission->status === 'completed') {
            return response()->json(['status' => 'error', 'message' => 'A missão já foi concluída.'], 422);
        }

        $rewardKvc = (float) $mission->reward_amount;
        if ($this->emittedThisMonth($request->user()) + $rewardKvc > WalletLedger::emissionLimitKvc()) {
            return response()->json(['status' => 'error', 'message' => 'Limite mensal de emissão de KVC atingido.'], 422);
        }

        $child = $mission->child;
        $wallet = Wallet::firstOrCreate(
            ['walletable_type' => ChildProfile::class, 'walletable_id' => $child->id],
            ['tenant_id' => $child->tenant_id, 'balance' => 0, 'currency' => 'AOA']
        );

        try {
            if ($rewardKvc > 0) {
                WalletLedger::move(
                    $wallet,
                    'credit',
                    $rewardKvc / WalletLedger::KVC_PER_AOA,
                    'mission_reward',
                    'Missão: ' . $mission->title,
                    'mission:' . $mission->id,
                    ['mission_id' => $mission->id]
                );
            }
        } catch (\DomainException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        $points = (int) $mission->kiva_points_reward;
        if ($points > 0) {
            $child->increment('total_xp', $points);
        }

        $mission->update(['status' => 'completed', 'completed_at' => now()]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'reward_coins' => $rewardKvc,
            'reward_points' => $points,
            'new_balance' => round((float) $wallet->fresh()->balance * WalletLedger::KVC_PER_AOA, 2),
        ]);
    }

    /** POST /missions/from-template — cria a missao da crianca a partir de um modelo publicado. */
    public function fromTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mission_template_id' => 'required|uuid|exists:mission_templates,id',
            'child_profile_id' => 'required|uuid|exists:child_profiles,id',
            'lesson_id' => 'nullable|uuid',
        ]);

        $this->ownedChild($request->user(), $data['child_profile_id']);
        $template = MissionTemplate::where('status', 'published')->findOrFail($data['mission_template_id']);

        $mission = Mission::firstOrCreate(
            ['child_id' => $data['child_profile_id'], 'mission_template_id' => $template->id, 'status' => 'available'],
            [
                'title' => $template->title,
                'description' => $template->description,
                'type' => 'custom',
                'reward_amount' => $template->base_coin_reward,
                'kiva_points_reward' => $template->base_xp_reward,
                'lesson_id' => $data['lesson_id'] ?? $template->lesson_id,
            ]
        );

        return response()->json(['status' => 'success', 'data' => $this->present($mission->load('child'))], 201);
    }

    /** GET /mission-templates */
    public function templates(): JsonResponse
    {
        $rows = MissionTemplate::where('status', 'published')->orderBy('title')->get();

        return response()->json(['status' => 'success', 'data' => $rows->map(fn (MissionTemplate $t) => [
            'id' => $t->id,
            'title' => $t->title,
            'description' => $t->description ?? '',
            'type' => 'custom',
            'difficulty' => 'easy',
            'reward_coins' => (int) $t->base_coin_reward,
            'reward_points' => (int) $t->base_xp_reward,
            'target_amount' => null,
            'age_group' => '',
            'lesson_id' => $t->lesson_id,
            'conditions' => $t->validation_rules ?? [],
            'is_active' => true,
            'created_by' => '',
            'created_at' => $t->created_at?->toISOString(),
            'updated_at' => $t->updated_at?->toISOString(),
        ])->values()]);
    }

    // ------------------------------------------------------------------ helpers

    /** Criancas que o utilizador pode ver: as suas (encarregado), as das suas turmas (professor), da escola (coordenador) ou todas (admin). */
    private function visibleChildren($query, $user)
    {
        if ($user->role === 'admin') {
            return $query;
        }

        return $query->where(function ($w) use ($user) {
            $w->where('parent_id', $user->id);
            if ($user->role === 'teacher') {
                $w->orWhereHas('classrooms', fn ($c) => $c->whereHas('teachers', fn ($t) => $t->where('users.id', $user->id)));
            }
            if (in_array($user->role, ['coordinator', 'coordenador'], true) && $user->tenant_id) {
                $w->orWhere('tenant_id', $user->tenant_id);
            }
        });
    }

    private function ownedChild($user, string $childId): ChildProfile
    {
        return ChildProfile::query()->tap(fn ($q) => $this->visibleChildren($q, $user))->findOrFail($childId);
    }

    private function ownedMission(Request $request, string $id): Mission
    {
        $user = $request->user();

        return Mission::query()->with('child')
            ->whereHas('child', fn ($c) => $this->visibleChildren($c, $user))
            ->findOrFail($id);
    }

    private function emittedThisMonth($user): float
    {
        $wallets = Wallet::query()->ownedBy($user)->where('walletable_type', ChildProfile::class)->pluck('id');

        return (float) LedgerEntry::whereIn('wallet_id', $wallets)->where('direction', 'credit')
            ->whereIn('entry_type', ['mission_reward', 'task_reward'])
            ->where('created_at', '>=', now()->startOfMonth())->sum('amount') * WalletLedger::KVC_PER_AOA;
    }

    private function present(Mission $m): array
    {
        $status = match ($m->status) {
            'completed', 'approved', 'validada' => 'completed',
            'in_progress' => 'in_progress',
            default => 'available',
        };

        return [
            'id' => $m->id,
            'title' => $m->title,
            'description' => $m->description ?? '',
            'type' => $m->type ?? 'custom',
            'target_amount' => $m->target_amount !== null ? (float) $m->target_amount : null,
            'reward' => (float) $m->reward_amount,
            'kiva_points_reward' => (int) $m->kiva_points_reward,
            'status' => $status,
            'child_profile_id' => $m->child_id,
            'parent_profile_id' => $m->child?->parent_id,
            'household_id' => $m->child?->family_id,
            'week' => (int) $m->created_at?->isoWeek(),
            'completed_at' => $m->completed_at?->toISOString(),
            'created_at' => $m->created_at?->toISOString(),
            'updated_at' => $m->updated_at?->toISOString(),
            'difficulty' => $m->difficulty,
            'expires_at' => $m->deadline?->toISOString(),
            'lesson_id' => $m->lesson_id,
            'mission_template_id' => $m->mission_template_id,
            'child_display_name' => $m->child?->name,
            'child_avatar' => $m->child?->avatar ?: '👧',
        ];
    }
}
