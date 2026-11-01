<?php

namespace App\Http\Controllers\Api\ParentGate;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Support\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Subscricao familiar: catalogo, plano atual, subscrever/cancelar (pago com a carteira
 * do encarregado), crianca extra e faturas.
 */
class SubscriptionController extends Controller
{
    public function tiers(): JsonResponse
    {
        return response()->json(['data' => SubscriptionPlan::where('is_active', true)->get()], 200);
    }

    /** GET /subscription — plano atual da familia (plano gratuito se nao houver). */
    public function current(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $plan = $tenant->subscription_tier_id ? SubscriptionPlan::find($tenant->subscription_tier_id) : null;

        return response()->json([
            'status' => 'success',
            'tier' => $plan ? $this->presentPlan($plan) : ['id' => null, 'name' => 'Free', 'tier_type' => 'free', 'features' => [], 'max_children' => null],
            'plan_status' => $plan ? 'active' : 'free',
            'billing_period' => $tenant->subscription_period,
            'ends_at' => $tenant->subscription_ends_at?->toISOString(),
            'extra_children' => (int) $tenant->extra_children,
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tier_id' => 'required|uuid|exists:subscription_plans,id',
            'billing_period' => 'nullable|in:monthly,yearly',
        ]);

        $user = $request->user();
        $tenant = $this->tenant($request);
        $plan = SubscriptionPlan::where('is_active', true)->findOrFail($data['tier_id']);

        if ($plan->type !== 'family') {
            return response()->json(['status' => 'error', 'message' => 'Este plano não está disponível para famílias.'], 422);
        }

        $period = $data['billing_period'] ?? 'monthly';
        $price = (float) ($period === 'yearly' ? $plan->annual_price : $plan->monthly_price);

        if ($price > 0) {
            $error = $this->charge($user, $price, "Subscrição {$plan->name} ({$period})");
            if ($error) {
                return response()->json(['status' => 'error', 'message' => $error], 422);
            }
        }

        $tenant->update([
            'subscription_tier_id' => $plan->id,
            'subscription_period' => $period,
            'subscription_ends_at' => $period === 'yearly' ? now()->addYear() : now()->addMonth(),
        ]);

        $this->invoice($user, $plan, $price, $period, "Subscrição {$plan->name}");

        return response()->json(['status' => 'success', 'message' => 'Subscrição ativada.', 'data' => ['tier' => $this->presentPlan($plan), 'billing_period' => $period]]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $this->tenant($request)->update(['subscription_tier_id' => null, 'subscription_period' => null, 'subscription_ends_at' => null]);

        return response()->json(['status' => 'success', 'message' => 'Subscrição cancelada com sucesso'], 200);
    }

    /** POST /subscription/add-extra-child — compra uma vaga extra de crianca (paga com a carteira). */
    public function addExtraChild(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $plan = $tenant->subscription_tier_id ? SubscriptionPlan::find($tenant->subscription_tier_id) : null;

        if (! $plan) {
            return response()->json(['status' => 'error', 'message' => 'Subscreva um plano para adicionar crianças extra.'], 422);
        }

        $price = (float) $plan->extra_child_price;
        if ($price > 0) {
            $error = $this->charge($request->user(), $price, 'Criança extra — ' . $plan->name);
            if ($error) {
                return response()->json(['status' => 'error', 'message' => $error], 422);
            }
            $this->invoice($request->user(), $plan, $price, 'one_time', 'Criança extra');
        }

        $tenant->increment('extra_children');

        return response()->json(['status' => 'success', 'message' => 'Criança extra adicionada.', 'data' => ['extra_children' => (int) $tenant->fresh()->extra_children]]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $familyId = $request->user()->family_id;
        $rows = $familyId ? SubscriptionInvoice::where('family_id', $familyId)->orderByDesc('created_at')->get() : collect();
        $plans = SubscriptionPlan::whereIn('id', $rows->pluck('tier_id')->filter())->get()->keyBy('id');
        $tenantId = $request->user()->tenant_id;

        return response()->json(['status' => 'success', 'data' => $rows->map(fn (SubscriptionInvoice $i) => [
            'id' => $i->id,
            'tenant_id' => $tenantId,
            'tier_id' => $i->tier_id,
            'amount' => (float) $i->amount,
            'currency' => $i->currency ?: 'AOA',
            'billing_period' => $i->billing_period,
            'status' => $i->status,
            'due_date' => $i->created_at?->toISOString(),
            'paid_at' => $i->paid_at?->toISOString(),
            'payment_method' => $i->payment_method,
            'payment_reference' => $i->payment_reference,
            'created_at' => $i->created_at?->toISOString(),
            'tier_name' => $plans[$i->tier_id]->name ?? $i->description,
            'tier_type' => isset($plans[$i->tier_id]) ? 'family_premium' : null,
        ])->values()], 200);
    }

    // ------------------------------------------------------------------ helpers

    private function tenant(Request $request): Tenant
    {
        $tenantId = $request->user()->tenant_id;
        abort_if(! $tenantId, 404, 'Sem família associada.');

        return Tenant::findOrFail($tenantId);
    }

    /** Debita a carteira do encarregado; devolve a mensagem de erro (ou null). */
    private function charge(User $user, float $amount, string $description): ?string
    {
        $wallet = Wallet::where('walletable_type', User::class)->where('walletable_id', $user->id)->first();
        if (! $wallet) {
            return 'Carregue a carteira antes de subscrever.';
        }

        try {
            WalletLedger::move($wallet, 'debit', $amount, 'subscription', $description, 'sub:' . Str::uuid());
        } catch (\DomainException $e) {
            return $e->getMessage() . ' Carregue a carteira e tente novamente.';
        }

        return null;
    }

    private function invoice(User $user, SubscriptionPlan $plan, float $amount, string $period, string $description): void
    {
        if (! $user->family_id) {
            return;
        }

        SubscriptionInvoice::create([
            'family_id' => $user->family_id,
            'tier_id' => $plan->id,
            'amount' => $amount,
            'currency' => $plan->currency ?: 'AOA',
            'billing_period' => $period,
            'description' => $description,
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => 'wallet',
            'payment_reference' => 'INV-' . strtoupper(Str::random(8)),
        ]);
    }

    private function presentPlan(SubscriptionPlan $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'tier_type' => $p->type === 'family' ? 'family_premium' : $p->type,
            'type' => $p->type,
            'price_monthly' => (float) $p->monthly_price,
            'price_yearly' => (float) $p->annual_price,
            'features' => $p->features ?? [],
            'max_children' => $p->max_children,
            'extra_child_price' => (float) $p->extra_child_price,
        ];
    }
}
