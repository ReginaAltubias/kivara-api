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
        ]);

        $program->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Programa atualizado com sucesso.',
            'data'    => $program,
        ], 200);
    }

    /**
     * Helper para identificar o parceiro autenticado
     */
    private function getAuthenticatedPartner(Request $request): Partner
    {
        $user = $request->user();

        if ($user->partner_id) {
            return Partner::findOrFail($user->partner_id);
        }

        if ($user->partner) {
            return $user->partner;
        }

        $firstPartner = Partner::first();
        if ($firstPartner) {
            return $firstPartner;
        }

        return Partner::create([
            'name'          => 'Banco Parceiro KIVARA',
            'contact_email' => $user->email,
            'is_active'     => true,
        ]);
    }
}
