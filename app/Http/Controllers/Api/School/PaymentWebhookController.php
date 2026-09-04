<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * POST /api/v1/school/subscription/webhook
     * 
     * Endpoint público para liquidação automática de faturas de subscrição B2B.
     * Suporta callbacks dos gateways de pagamento angolanos (Multicaixa Express / EMIS / Proxypay).
     */
        #[OA\Post(
        path: "/api/v1/school/subscription/webhook",
        summary: "School Subscription Webhook (POST)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function handle(Request $request): JsonResponse
    {
        Log::info('Subscription Webhook Received', ['payload' => $request->all()]);

        $reference = $request->input('payment_reference') 
                  ?? $request->input('reference') 
                  ?? $request->input('transaction_id')
                  ?? $request->input('invoice_id');

        if (!$reference) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Referência de pagamento não fornecida.',
            ], 422);
        }

        $result = DB::transaction(function () use ($reference, $request) {
            // 1. Localizar e bloquear a fatura para evitar processamento concorrente
            $invoice = Invoice::where('payment_reference', $reference)
                ->orWhere('id', $reference)
                ->lockForUpdate()
                ->first();

            if (!$invoice) {
                return [
                    'success' => false,
                    'code'    => 404,
                    'message' => 'Fatura não encontrada para a referência fornecida.',
                ];
            }

            // Se já estiver paga, responder com sucesso (idempotência)
            if ($invoice->status === 'paid') {
                return [
                    'success' => true,
                    'code'    => 200,
                    'message' => 'Fatura já se encontra liquidada.',
                ];
            }

            // 2. Marcar a fatura como paga
            $invoice->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);

            // 3. Renovar / Estender a subscrição da escola
            $plan = $invoice->plan ?? Plan::find($invoice->plan_id);
            $tenantId = $invoice->tenant_id;

            $durationMonths = ($plan && $plan->billing_cycle === 'yearly') ? 12 : 1;

            $subscription = Subscription::where('tenant_id', $tenantId)
                ->latest('created_at')
                ->first();

            $now = now();
            $baseDate = ($subscription && $subscription->isActive() && $subscription->ends_at)
                ? $subscription->ends_at
                : $now;

            $newEndsAt = (clone $baseDate)->addMonths($durationMonths);

            if ($subscription) {
                $subscription->update([
                    'plan_id'   => $plan ? $plan->id : $subscription->plan_id,
                    'status'    => 'active',
                    'starts_at' => $subscription->starts_at ?? $now,
                    'ends_at'   => $newEndsAt,
                ]);
            } else {
                $subscription = Subscription::create([
                    'tenant_id' => $tenantId,
                    'plan_id'   => $plan ? $plan->id : Plan::first()->id,
                    'status'    => 'active',
                    'starts_at' => $now,
                    'ends_at'   => $newEndsAt,
                ]);
            }

            $invoice->update(['subscription_id' => $subscription->id]);

            return [
                'success' => true,
                'code'    => 200,
                'message' => "Fatura {$invoice->id} liquidada. Subscrição da escola ativa até " . $newEndsAt->toFormattedDateString() . '.',
            ];
        });

        return response()->json([
            'status'  => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ], $result['code']);
    }
}
