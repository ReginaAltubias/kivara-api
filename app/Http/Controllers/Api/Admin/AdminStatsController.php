<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\DreamVault;
use App\Models\LedgerEntry;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminStatsController extends Controller
{
    public function index(): JsonResponse
    {
        $byType = Tenant::select('tenant_type', DB::raw('COUNT(*) as total'))
            ->groupBy('tenant_type')->pluck('total', 'tenant_type');

        $totalTasks = Task::count();
        $completedTasks = Task::whereIn('status', ['completed', 'approved', 'done'])->count();

        $today = now()->startOfDay();
        $dau = DB::table('personal_access_tokens')->where('last_used_at', '>=', $today)
            ->distinct()->count('tokenable_id');

        $sparkline = collect(range(6, 0))->map(function (int $daysAgo) {
            $day = now()->subDays($daysAgo)->startOfDay();
            $entries = LedgerEntry::whereBetween('created_at', [$day, $day->copy()->endOfDay()]);

            return [
                'day' => $day->format('D'),
                'volume' => (float) (clone $entries)->sum('amount'),
                'count' => (clone $entries)->count(),
            ];
        })->values();

        $todayEntries = LedgerEntry::where('created_at', '>=', $today);

        $emitted = (float) LedgerEntry::where('direction', 'credit')->sum('amount');
        $burned = (float) LedgerEntry::where('direction', 'debit')->sum('amount');

        return response()->json([
            'status' => 'success',
            'data' => [
                'totalTenants' => Tenant::count(),
                'activeTenants' => Tenant::where('is_active', true)->count(),
                'tenantsByType' => [
                    'family' => (int) ($byType['family'] ?? 0),
                    'school' => (int) ($byType['school'] ?? 0),
                    'institutional_partner' => (int) (($byType['institutional_partner'] ?? 0) + ($byType['partner'] ?? 0)),
                ],
                'totalUsers' => User::count(),
                'totalChildren' => ChildProfile::count(),
                'totalWallets' => Wallet::count(),
                'dau' => $dau,
                'missionCompletionRate' => $totalTasks > 0 ? round($completedTasks / $totalTasks * 100, 1) : 0,
                'totalTasks' => $totalTasks,
                'completedTasks' => $completedTasks,
                'dailyTxVolume' => (float) (clone $todayEntries)->sum('amount'),
                'dailyTxCount' => (clone $todayEntries)->count(),
                'weeklySparkline' => $sparkline,
                'money_supply' => [
                    'total_emitted' => $emitted,
                    'total_burned' => $burned,
                    'total_in_circulation' => $emitted - $burned,
                    'total_in_wallets' => (float) Wallet::sum('balance'),
                    'total_in_vaults' => (float) DreamVault::sum('current_amount'),
                    'wallet_count' => Wallet::count(),
                    'system_wallet_id' => '',
                    'audit_timestamp' => now()->toISOString(),
                ],
            ],
        ]);
    }
}
