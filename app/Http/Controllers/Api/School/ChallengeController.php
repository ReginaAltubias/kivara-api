<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\PartnerChallenge;
use App\Models\SchoolReward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChallengeController extends Controller
{
    /**
     * GET /api/v1/school/challenges ou /api/v1/b2b/challenges
     */
        #[OA\Get(
        path: "/api/v1/school/challenges",
        summary: "School Challenges (GET)",
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
        $challenges = $this->scoped($request)->with(['schoolReward', 'classrooms:id,name,grade_level'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($c) {
                return [
                    'id'                        => $c->id,
                    'title'                     => $c->title,
                    'description'               => $c->description,
                    'icon'                      => '🏆',
                    'type'                      => $c->goal_type ?? 'habit',
                    'status'                    => $c->status ?? 'active',
                    'start_date'                => $c->starts_at?->toISOString(),
                    'end_date'                  => $c->ends_at?->toISOString(),
                    'target_amount'             => $c->goal_target,
                    'reward'                    => $c->reward_pool,
                    'kiva_points_reward'        => 500,
                    'school_reward_id'          => $c->school_reward_id,
                    'school_reward_description' => $c->school_reward_description ?? $c->schoolReward?->name,
                    'classroom_ids'             => $c->classrooms->pluck('id')->toArray(),
                    'classrooms_count'          => $c->classrooms->count(),
                    'current_amount'            => (int) $c->classrooms->sum(fn ($cl) => (int) ($cl->pivot->current_progress ?? 0)),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => $challenges,
        ], 200);
    }

    /**
     * POST /api/v1/school/challenges
     * 
     * Criação de novo desafio coletivo (Contrato CreateChallengeModal.tsx).
     */
        #[OA\Post(
        path: "/api/v1/school/challenges",
        summary: "School Challenges (POST)",
        tags: ["B2B - Escolas & Professores"],
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
        abort_unless($request->user()->teacherCan('can_assign_challenges'), 403, 'Sem permissão para criar desafios.');

        $validated = $request->validate([
            'title'                     => 'required|string|max:255',
            'description'               => 'nullable|string|max:1000',
            'type'                      => 'nullable|string|in:habit,quiz,saving,total_xp,lessons_completed',
            'status'                    => 'nullable|string|in:active,draft,scheduled',
            'start_date'                => 'nullable|date',
            'end_date'                  => 'nullable|date',
            'target_amount'             => 'nullable|integer|min:0',
            'reward'                    => 'nullable|integer|min:0',
            'kiva_points_reward'        => 'nullable|integer|min:0',
            'school_reward_id'          => 'nullable|uuid|exists:school_rewards,id',
            'school_reward_description' => 'nullable|string|max:255',
            'classroom_ids'             => 'nullable|array',
            'classroom_ids.*'           => 'uuid|exists:classrooms,id',
        ]);

        $partnerName = $request->user()->school_name ?? $request->user()->name ?? 'Escola KIVARA';
        $tenantId = $request->user()->tenant_id;

        if (!empty($validated['classroom_ids']) && $request->user()->role !== 'admin') {
            $own = Classroom::where('tenant_id', $tenantId)->whereIn('id', $validated['classroom_ids'])->count();
            if ($own !== count(array_unique($validated['classroom_ids']))) {
                abort(422, 'Só pode associar turmas da sua escola.');
            }
        }

        $goalType = $validated['type'] ?? 'total_xp';
        if (!in_array($goalType, ['total_xp', 'lessons_completed', 'savings_amount'])) {
            $goalType = 'total_xp';
        }

        $challenge = DB::transaction(function () use ($validated, $partnerName, $goalType, $tenantId) {
            $ch = PartnerChallenge::create([
                'partner_name'              => $partnerName,
                'tenant_id'                 => $tenantId,
                'title'                     => $validated['title'],
                'description'               => $validated['description'] ?? null,
                'goal_type'                 => $goalType,
                'goal_target'               => $validated['target_amount'] ?? 100,
                'reward_pool'               => $validated['reward'] ?? 50000,
                'starts_at'                 => $validated['start_date'] ?? now(),
                'ends_at'                   => $validated['end_date'] ?? now()->addMonth(),
                'status'                    => $validated['status'] ?? 'active',
                'school_reward_id'          => $validated['school_reward_id'] ?? null,
                'school_reward_description' => $validated['school_reward_description'] ?? null,
            ]);

            // Se foram especificadas turmas, associa-as
            if (!empty($validated['classroom_ids'])) {
                foreach ($validated['classroom_ids'] as $clsId) {
                    $ch->classrooms()->attach($clsId, [
                        'id'               => (string) Str::uuid(),
                        'status'           => 'active',
                        'current_progress' => 0,
                    ]);
                }
            }

            return $ch;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Desafio criado com sucesso.',
            'data'    => $challenge->load(['schoolReward', 'classrooms']),
        ], 201);
    }

    /**
     * PATCH /api/v1/school/challenges/{id}
     */
        #[OA\Patch(
        path: "/api/v1/school/challenges/{id}",
        summary: "School Challenges {id} (PATCH)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $challenge = $this->scoped($request)->findOrFail($id);

        $validated = $request->validate([
            'title'                     => 'sometimes|required|string|max:255',
            'description'               => 'nullable|string|max:1000',
            'status'                    => 'nullable|string|in:active,draft,scheduled,ended',
            'start_date'                => 'nullable|date',
            'end_date'                  => 'nullable|date',
            'target_amount'             => 'nullable|integer|min:0',
            'reward'                    => 'nullable|integer|min:0',
            'school_reward_id'          => 'nullable|uuid|exists:school_rewards,id',
            'school_reward_description' => 'nullable|string|max:255',
        ]);

        $challenge->update([
            'title'                     => $validated['title'] ?? $challenge->title,
            'description'               => $validated['description'] ?? $challenge->description,
            'status'                    => $validated['status'] ?? $challenge->status,
            'starts_at'                 => $validated['start_date'] ?? $challenge->starts_at,
            'ends_at'                   => $validated['end_date'] ?? $challenge->ends_at,
            'goal_target'               => $validated['target_amount'] ?? $challenge->goal_target,
            'reward_pool'               => $validated['reward'] ?? $challenge->reward_pool,
            'school_reward_id'          => $validated['school_reward_id'] ?? $challenge->school_reward_id,
            'school_reward_description' => $validated['school_reward_description'] ?? $challenge->school_reward_description,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Desafio atualizado com sucesso.',
            'data'    => $challenge,
        ], 200);
    }

    /**
     * DELETE /api/v1/school/challenges/{id}
     */
        #[OA\Delete(
        path: "/api/v1/school/challenges/{id}",
        summary: "School Challenges {id} (DELETE)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 204, description: "Recurso removido com sucesso.")
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $challenge = $this->scoped($request)->findOrFail($id);
        $challenge->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Desafio eliminado com sucesso.',
        ], 200);
    }

    /**
     * POST /api/v1/b2b/challenges/enroll
     */
        #[OA\Post(
        path: "/api/v1/b2b/challenges/enroll",
        summary: "B2b Challenges Enroll (POST)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function enroll(Request $request): JsonResponse
    {
        $request->validate([
            'classroom_id'         => 'required|uuid|exists:classrooms,id',
            'partner_challenge_id' => 'required|uuid|exists:partner_challenges,id',
        ]);

        $user = $request->user();
        
        $classroom = Classroom::where('tenant_id', $user->tenant_id)
            ->where(function ($q) use ($user) {
                $q->where('tenant_id', $user->tenant_id)
                  ->orWhereHas('teachers', fn($t) => $t->where('users.id', $user->id));
            })->findOrFail($request->classroom_id);

        $challenge = PartnerChallenge::findOrFail($request->partner_challenge_id);

        if (!$classroom->challenges()->where('partner_challenge_id', $challenge->id)->exists()) {
            $classroom->challenges()->attach($challenge->id, [
                'id'               => (string) Str::uuid(),
                'status'           => 'active',
                'current_progress' => 0,
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Turma inscrita com sucesso no desafio.',
        ], 200);
    }

    /**
     * GET /api/v1/school/challenges/{id}/leaderboard
     */
        #[OA\Get(
        path: "/api/v1/school/challenges/{id}/leaderboard",
        summary: "School Challenges {id} Leaderboard (GET)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function leaderboard(string $challengeId): JsonResponse
    {
        $leaderboard = DB::table('classroom_challenge')
            ->join('classrooms', 'classroom_challenge.classroom_id', '=', 'classrooms.id')
            ->where('classroom_challenge.partner_challenge_id', $challengeId)
            ->select('classrooms.name as classroom_name', 'classrooms.grade_level', 'classroom_challenge.current_progress')
            ->orderByDesc('classroom_challenge.current_progress')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $leaderboard,
        ], 200);
    }

    /**
     * Desafios visiveis ao utilizador: os criados pela sua escola ou aqueles em que
     * participam turmas da sua escola. O admin ve todos.
     */
    private function scoped(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $user = $request->user();
        $query = PartnerChallenge::query();

        if ($user->role !== 'admin') {
            $tenantId = $user->tenant_id;
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereHas('classrooms', fn ($c) => $c->where('classrooms.tenant_id', $tenantId));
            });
        }

        return $query;
    }
}
