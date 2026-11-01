<?php

namespace App\Http\Controllers\Api\Partner;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Partner;
use App\Models\PartnerProgram;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerProgramController extends Controller
{
    /**
     * GET /api/v1/partner/programs
     * 
     * Lista todos os programas de patrocínio do parceiro autenticado.
     */
        #[OA\Get(
        path: "/api/v1/partner/programs",
        summary: "Programas de Patrocínio",
        tags: ["B2B - Parceiros & Patrocinadores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $partner = $this->getAuthenticatedPartner($request);

        $programs = PartnerProgram::withCount('challenges')
            ->where('partner_id', $partner->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($p) {
                return [
                    'id'               => $p->id,
                    'title'            => $p->title,
                    'description'      => $p->description,
                    'allocated_budget' => (int) $p->allocated_budget,
                    'committed_budget' => $p->committedBudget(),
                    'remaining_budget' => $p->remainingBudget(),
                    'status'           => $p->status,
                    'challenges_count' => (int) $p->challenges_count,
                    'program_type'     => $p->program_type,
                    'children_target'  => (int) $p->children_target,
                    'starts_at'        => $p->starts_at?->toISOString(),
                    'ends_at'          => $p->ends_at?->toISOString(),
                    'created_at'       => $p->created_at?->toISOString(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => $programs,
        ], 200);
    }

    /**
     * POST /api/v1/partner/programs
     * 
     * Cria um novo programa e aloca orçamento cativo da carteira KVC do parceiro.
     */
        #[OA\Post(
        path: "/api/v1/partner/programs",
        summary: "Criar Programa de Patrocínio",
        tags: ["B2B - Parceiros & Patrocinadores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $partner = $this->getAuthenticatedPartner($request);

        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string|max:1000',
            'allocated_budget' => 'required|integer|min:0',
            'program_type'     => 'nullable|in:school,family',
            'children_target'  => 'nullable|integer|min:0',
            'starts_at'        => 'nullable|date',
            'ends_at'          => 'nullable|date|after_or_equal:starts_at',
        ]);

        $allocatedBudget = (int) $validated['allocated_budget'];

        $program = DB::transaction(function () use ($partner, $validated, $allocatedBudget) {
            if ($allocatedBudget > 0) {
                // Obter a carteira KVC do parceiro com bloqueio pessimista
                $wallet = Wallet::where('walletable_type', Partner::class)
                    ->where('walletable_id', $partner->id)
                    ->where('currency', 'KVC')
                    ->lockForUpdate()
                    ->first();

                $currentBalance = $wallet ? (float) $wallet->balance : 0;

                if ($currentBalance < $allocatedBudget) {
                    abort(422, "Saldo insuficiente na carteira geral do parceiro. Saldo disponível: " . number_format($currentBalance, 0, ',', '.') . " KVC.");
                }

                // Deduzir da carteira geral para alocar ao programa
                $wallet->decrement('balance', $allocatedBudget);

                $program = PartnerProgram::create([
                    'partner_id'       => $partner->id,
                    'title'            => $validated['title'],
                    'description'      => $validated['description'] ?? null,
                    'allocated_budget' => $allocatedBudget,
                    'status'           => 'active',
                    'program_type'     => $validated['program_type'] ?? 'school',
                    'children_target'  => $validated['children_target'] ?? 0,
                    'starts_at'        => $validated['starts_at'] ?? now(),
                    'ends_at'          => $validated['ends_at'] ?? null,
                ]);

                // Registar saída da carteira geral para o programa
                LedgerEntry::create([
                    'wallet_id'       => $wallet->id,
                    'idempotency_key' => "prog_alloc:{$program->id}",
                    'direction'       => 'debit',
                    'entry_type'      => 'adjustment',
                    'amount'          => $allocatedBudget,
                    'description'     => "Alocação de Orçamento ao Programa: {$program->title}",
                    'metadata'        => [
                        'partner_id' => $partner->id,
                        'program_id' => $program->id,
                    ],
                    'created_at'      => now(),
                ]);

                return $program;
            }

            return PartnerProgram::create([
                'partner_id'       => $partner->id,
                'title'            => $validated['title'],
                'description'      => $validated['description'] ?? null,
                'allocated_budget' => 0,
                'status'           => 'active',
                'program_type'     => $validated['program_type'] ?? 'school',
                'children_target'  => $validated['children_target'] ?? 0,
                'starts_at'        => $validated['starts_at'] ?? now(),
                'ends_at'          => $validated['ends_at'] ?? null,
            ]);
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Programa criado com sucesso e orçamento alocado.',
            'data'    => [
                'id'               => $program->id,
                'title'            => $program->title,
                'description'      => $program->description,
                'allocated_budget' => (int) $program->allocated_budget,
                'committed_budget' => $program->committedBudget(),
                'remaining_budget' => $program->remainingBudget(),
                'status'           => $program->status,
                'program_type'     => $program->program_type,
                'children_target'  => (int) $program->children_target,
                'starts_at'        => $program->starts_at?->toISOString(),
                'ends_at'          => $program->ends_at?->toISOString(),
                'created_at'       => $program->created_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * GET /api/v1/partner/programs/{id}
     */
        #[OA\Get(
        path: "/api/v1/partner/programs/{id}",
        summary: "Programas de Patrocínio",
        tags: ["B2B - Parceiros & Patrocinadores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $partner = $this->getAuthenticatedPartner($request);

        $program = PartnerProgram::with(['challenges'])
            ->where('partner_id', $partner->id)
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'               => $program->id,
                'title'            => $program->title,
                'description'      => $program->description,
                'allocated_budget' => (int) $program->allocated_budget,
                'committed_budget' => $program->committedBudget(),
                'remaining_budget' => $program->remainingBudget(),
                'status'           => $program->status,
                'challenges'       => $program->challenges,
                'program_type'     => $program->program_type,
                'children_target'  => (int) $program->children_target,
                'starts_at'        => $program->starts_at?->toISOString(),
                'ends_at'          => $program->ends_at?->toISOString(),
                'created_at'       => $program->created_at?->toISOString(),
            ],
        ], 200);
    }

    /**
     * PATCH /api/v1/partner/programs/{id}
     */
        #[OA\Patch(
        path: "/api/v1/partner/programs/{id}",
        summary: "Programas de Patrocínio",
        tags: ["B2B - Parceiros & Patrocinadores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $partner = $this->getAuthenticatedPartner($request);

        $program = PartnerProgram::where('partner_id', $partner->id)->findOrFail($id);

        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status'      => 'nullable|string|in:active,completed',
            'program_type'    => 'nullable|in:school,family',
            'children_target' => 'nullable|integer|min:0',
            'starts_at'       => 'nullable|date',
            'ends_at'         => 'nullable|date',
        ]);

        DB::transaction(function () use ($program, $validated, $partner) {
            // Ao concluir, o orcamento ainda nao cativado volta para a carteira do parceiro.
            if (($validated['status'] ?? null) === 'completed' && $program->status === 'active') {
                $remaining = $program->remainingBudget();
                if ($remaining > 0) {
                    $this->refundToWallet($partner, $remaining, "prog_release:{$program->id}", "Libertação de orçamento do Programa: {$program->title}", $program->id);
                    $validated['allocated_budget'] = $program->allocated_budget - $remaining;
                }
            }

            $program->update($validated);
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Programa atualizado com sucesso.',
            'data'    => $program->fresh(),
        ], 200);
    }

    /**
     * Helper para identificar o parceiro autenticado
     */
    /** DELETE /api/v1/partner/programs/{id} — so sem desafios; devolve o orcamento a carteira. */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $partner = $this->getAuthenticatedPartner($request);
        $program = PartnerProgram::where('partner_id', $partner->id)->findOrFail($id);

        if ($program->challenges()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'O programa tem desafios. Cancele ou elimine os desafios, ou conclua o programa.',
            ], 409);
        }

        DB::transaction(function () use ($program, $partner) {
            if ($program->allocated_budget > 0 && $program->status === 'active') {
                $this->refundToWallet($partner, (int) $program->allocated_budget, "prog_delete:{$program->id}", "Devolução do orçamento do Programa eliminado: {$program->title}", $program->id);
            }
            $program->delete();
        });

        return response()->json(['status' => 'success', 'message' => 'Programa eliminado e orçamento devolvido à carteira.']);
    }

    private function refundToWallet(Partner $partner, int $amount, string $key, string $description, string $programId): void
    {
        $wallet = Wallet::where('walletable_type', Partner::class)
            ->where('walletable_id', $partner->id)
            ->where('currency', 'KVC')
            ->lockForUpdate()
            ->firstOrFail();

        $wallet->increment('balance', $amount);

        LedgerEntry::create([
            'wallet_id'       => $wallet->id,
            'idempotency_key' => $key,
            'direction'       => 'credit',
            'entry_type'      => 'adjustment',
            'amount'          => $amount,
            'description'     => $description,
            'metadata'        => ['partner_id' => $partner->id, 'program_id' => $programId],
            'created_at'      => now(),
        ]);
    }

    private function getAuthenticatedPartner(Request $request): Partner
    {
        return \App\Support\PartnerContext::resolve($request);
    }
}
