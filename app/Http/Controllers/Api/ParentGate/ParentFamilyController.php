<?php

namespace App\Http\Controllers\Api\ParentGate;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\Consent;
use App\Models\LedgerEntry;
use App\Models\Streak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentFamilyController extends Controller
{
    /** GET /api/v1/parent/streaks — series de actividade dos filhos do encarregado. */
    public function streaks(Request $request): JsonResponse
    {
        $user = $request->user();

        $children = ChildProfile::query()
            ->when($user->role !== 'admin', fn ($q) => $q->where('parent_id', $user->id))
            ->when($request->filled('profiles'), fn ($q) => $q->whereIn('id', explode(',', (string) $request->query('profiles'))))
            ->get(['id', 'name', 'avatar']);

        $streaks = Streak::whereIn('child_profile_id', $children->pluck('id'))->get()->keyBy('child_profile_id');

        // Dias distintos com pelo menos uma licao concluida
        $activeDays = \App\Models\UserProgress::whereIn('child_profile_id', $children->pluck('id'))
            ->where('completed', true)->whereNotNull('completed_at')
            ->get(['child_profile_id', 'completed_at'])
            ->groupBy('child_profile_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => $r->completed_at->toDateString())->unique()->count());

        return response()->json([
            'status' => 'success',
            'data' => $children->map(function (ChildProfile $c) use ($streaks, $activeDays) {
                $s = $streaks->get($c->id);

                return [
                    'profile_id' => $c->id,
                    'child_profile_id' => $c->id,
                    'name' => $c->name,
                    'avatar' => $c->avatar,
                    'current_streak' => (int) ($s?->current_streak ?? 0),
                    'longest_streak' => (int) ($s?->best_streak ?? 0),
                    'total_active_days' => (int) ($activeDays[$c->id] ?? 0),
                    'last_activity_at' => $s?->last_activity_at?->toISOString(),
                ];
            })->values(),
        ]);
    }

    /** POST /api/v1/parent/children/{id}/export — exportacao dos dados do filho (RGPD/LGPD). */
    public function export(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $child = ChildProfile::query()
            ->when($user->role !== 'admin', fn ($q) => $q->where('parent_id', $user->id))
            ->findOrFail($id);

        $walletIds = $child->wallets()->pluck('id');

        return response()->json([
            'status' => 'success',
            'exported_at' => now()->toISOString(),
            'data' => [
                'profile' => $child->only(['id', 'name', 'nickname', 'username', 'avatar', 'date_of_birth', 'kivara_phase_id', 'total_xp', 'monthly_budget', 'daily_spend_limit', 'created_at']),
                'consents' => Consent::where('child_profile_id', $child->id)->get(),
                'wallet_entries' => LedgerEntry::whereIn('wallet_id', $walletIds)->orderBy('created_at')->get(['id', 'direction', 'entry_type', 'amount', 'description', 'created_at']),
                'streak' => Streak::where('child_profile_id', $child->id)->first(),
                'tasks' => $child->tasks()->get(),
            ],
        ]);
    }
}
