<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class B2BSubscriptionController extends Controller
{
    /**
     * GET /api/v1/school/subscription
     * 
     * Retorna a assinatura atual da escola, dias restantes, histórico de faturas e planos disponíveis.
     */
        #[OA\Get(
        path: "/api/v1/school/subscription",
        summary: "School Subscription (GET)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $tenant = Tenant::findOrFail($tenantId);

        // Subscrição atual mais recente
        $currentSubscription = Subscription::with('plan')
            ->where('tenant_id', $tenantId)
            ->latest('created_at')
            ->first();

        // Histórico de faturas
        $invoices = Invoice::with('plan:id,name')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($inv) {
                return [
                    'id'                => $inv->id,
                    'amount'            => (float) $inv->amount,
                    'status'            => $inv->status,
                    'payment_method'    => $inv->payment_method,
                    'payment_reference' => $inv->payment_reference,
                    'payment_payload'   => $inv->payment_payload,
                    'plan_name'         => $inv->plan?->name,
                    'paid_at'           => $inv->paid_at?->toISOString(),
                    'created_at'        => $inv->created_at?->toISOString(),
                ];
            });

        // Planos disponíveis
        $plans = Plan::active()->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'subscription' => $currentSubscription ? [
                    'id'             => $currentSubscription->id,
                    'plan_id'        => $currentSubscription->plan_id,
                    'plan_name'      => $currentSubscription->plan?->name,
                    'status'         => $currentSubscription->status,
                    'starts_at'      => $currentSubscription->starts_at?->toISOString(),
                    'ends_at'        => $currentSubscription->ends_at?->toISOString(),
                    'days_remaining' => $currentSubscription->daysRemaining(),
                    'is_active'      => $currentSubscription->isActive(),
                ] : null,
                'invoices'     => $invoices,
                'plans'        => $plans,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/school/subscription/checkout
     * 
     * Inicia o processo de subscrição/renovação com geração de fatura e dados de pagamento (Multicaixa Express / Referência).
     */
        #[OA\Post(
        path: "/api/v1/school/subscription/checkout",
        summary: "School Subscription Checkout (POST)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function checkout(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $tenant = Tenant::findOrFail($tenantId);

        $validated = $request->validate([
            'plan_id'        => 'required|uuid|exists:plans,id',
            'payment_method' => 'required|in:multicaixa,reference,mcx_express',
            'phone'          => 'nullable|string|max:20',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $paymentMethod = $validated['payment_method'];

        // Geração da referência e payload do gateway angolano (Multicaixa / EMIS)
        $paymentReference = null;
        $paymentPayload = [];

        if ($paymentMethod === 'reference') {
            // Pagamento por Referência Multicaixa
            $entity = '00543'; // Entidade KIVARA
            $refNumber = (string) mt_rand(100000000, 999999999);
            $paymentReference = "{$entity}-{$refNumber}";
            $paymentPayload = [
                'entity'     => $entity,
                'reference'  => $refNumber,
                'amount'     => (float) $plan->price,
                'expires_at' => now()->addDays(3)->toISOString(),
            ];
        } else {
            // Multicaixa Express (Pop-up Push no telemóvel)
            $phone = $validated['phone'] ?? $request->user()->phone ?? '923000000';
            $transactionId = 'MCX-' . strtoupper(Str::random(10));
            $paymentReference = $transactionId;
            $paymentPayload = [
                'phone'          => $phone,
                'transaction_id' => $transactionId,
                'prompt_sent'    => true,
                'message'        => 'Notificação enviada para o terminal Multicaixa Express.',
            ];
        }

        // Criar a fatura pendente
        $invoice = Invoice::create([
            'tenant_id'         => $tenantId,
            'plan_id'           => $plan->id,
            'amount'            => $plan->price,
            'status'            => 'pending',
            'payment_method'    => $paymentMethod,
            'payment_reference' => $paymentReference,
            'payment_payload'   => $paymentPayload,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Fatura gerada com sucesso. Aguarda liquidação.',
            'data'    => [
                'invoice_id'        => $invoice->id,
                'amount'            => (float) $invoice->amount,
                'payment_method'    => $paymentMethod,
                'payment_reference' => $paymentReference,
                'payment_data'      => $paymentPayload,
            ],
        ], 201);
    }

    /**
     * Helper para obter o tenant_id
     */
    private function getTenantId(Request $request): string
    {
        $tenantId = $request->user()->tenant_id;

        if (!$tenantId) {
            abort(403, 'O utilizador autenticado não está associado a nenhuma instituição de ensino.');
        }

        return $tenantId;
    }
}
