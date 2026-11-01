<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\SubscriptionPlan;
use App\Models\Wallet;
use App\Support\PartnerContext;
use App\Support\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Subscricao do parceiro, paga com a carteira KVC do parceiro (1 AOA = 10 KVC). */
class PartnerSubscriptionController extends Controller
{
    public function current(Request $request): JsonResponse
    {
        $partner = PartnerContext::resolve($request);
        $plan = $partner->subscription_tier_id ? SubscriptionPlan::find($partner->subscription_tier_id) : null;
        $ends = $partner->subscription_ends_at ? \Carbon\Carbon::parse($partner->subscription_ends_at) : null;

        return response()->json([
            'status' => 'success',
            'data' => [
                'tier' => $plan ? ['id' => $plan->id, 'name' => $plan->name, 'features' => $plan->features ?? []] : null,
                'billing_period' => $partner->subscription_period,
                'ends_at' => $ends?->toISOString(),
                'days_remaining' => $ends ? (int) max(now()->diffInDays($ends, false), 0) : null,
                'is_active' => (bool) ($plan && $ends && $ends->isFuture()),
            ],
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $partner = PartnerContext::resolve($request);
        $data = $request->validate([
            'tier_id' => 'required|uuid|exists:subscription_plans,id',
            'billing_period' => 'nullable|in:monthly,yearly',
        ]);

        $plan = SubscriptionPlan::where('is_active', true)->findOrFail($data['tier_id']);
        if ($plan->type !== 'partner') {
            return response()->json(['status' => 'error', 'message' => 'Este plano não está disponível para parceiros.'], 422);
        }

        $period = $data['billing_period'] ?? 'monthly';
        $priceAoa = (float) ($period === 'yearly' ? $plan->annual_price : $plan->monthly_price);

        if ($priceAoa > 0) {
            $wallet = Wallet::where('walletable_type', Partner::class)->where('walletable_id', $partner->id)->where('currency', 'KVC')->first();
            if (! $wallet) {
                return response()->json(['status' => 'error', 'message' => 'Carregue a carteira antes de subscrever.'], 422);
            }
            try {
                WalletLedger::move($wallet, 'debit', $priceAoa * WalletLedger::KVC_PER_AOA, 'subscription', "Subscrição {$plan->name} ({$period})", 'psub:' . Str::uuid());
            } catch (\DomainException $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage() . ' Carregue a carteira e tente novamente.'], 422);
            }
        }

        DB::transaction(function () use ($partner, $plan, $period, $priceAoa) {
            $partner->update([
                'subscription_tier_id' => $plan->id,
                'subscription_period' => $period,
                'subscription_ends_at' => $period === 'yearly' ? now()->addYear() : now()->addMonth(),
            ]);
            DB::table('partner_invoices')->insert([
                'id' => (string) Str::uuid(), 'partner_id' => $partner->id, 'tier_id' => $plan->id, 'amount' => $priceAoa, 'currency' => 'AOA',
                'billing_period' => $period, 'description' => "Subscrição {$plan->name}", 'status' => 'paid', 'paid_at' => now(),
                'payment_method' => 'wallet', 'payment_reference' => 'INV-' . strtoupper(Str::random(8)), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return response()->json(['status' => 'success', 'message' => 'Subscrição ativada.', 'data' => ['tier' => ['id' => $plan->id, 'name' => $plan->name], 'billing_period' => $period]]);
    }

    public function cancel(Request $request): JsonResponse
    {
        PartnerContext::resolve($request)->update(['subscription_tier_id' => null, 'subscription_period' => null, 'subscription_ends_at' => null]);

        return response()->json(['status' => 'success', 'message' => 'Subscrição cancelada.']);
    }

    public function invoices(Request $request): JsonResponse
    {
        $partner = PartnerContext::resolve($request);
        $rows = DB::table('partner_invoices')->where('partner_id', $partner->id)->orderByDesc('created_at')->get();
        $plans = SubscriptionPlan::whereIn('id', $rows->pluck('tier_id')->filter())->get()->keyBy('id');

        return response()->json(['status' => 'success', 'data' => $rows->map(fn ($i) => [
            'id' => $i->id,
            'tenant_id' => $partner->id,
            'tier_id' => $i->tier_id,
            'amount' => (float) $i->amount,
            'currency' => $i->currency,
            'billing_period' => $i->billing_period,
            'status' => $i->status,
            'due_date' => $i->created_at,
            'paid_at' => $i->paid_at,
            'payment_method' => $i->payment_method,
            'payment_reference' => $i->payment_reference,
            'created_at' => $i->created_at,
            'tier_name' => $plans[$i->tier_id]->name ?? $i->description,
            'tier_type' => 'partner_program',
        ])->values()]);
    }
}
