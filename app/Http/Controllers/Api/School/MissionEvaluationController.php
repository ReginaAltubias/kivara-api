<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\Classroom;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\UserProgress;
use App\Models\Wallet;
use App\Notifications\MissionEvaluatedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MissionEvaluationController
 *
 * Gere o fluxo completo de avaliação de missões pedagógicas:
 *  - Listagem de submissões pendentes por turma (FIFO com eager loading)
 *  - Avaliação transacional com bloqueio pessimista (lockForUpdate)
 *  - Recompensa e crédito transacional na Carteira / Ledger
 *  - Notificações Database e Expo Push ao aluno
 */
class MissionEvaluationController extends Controller
{
    /**
     * GET /api/v1/school/classrooms/{id}/pending-evaluations
     * 
     * Retorna a fila de avaliação da turma (apenas status = pending_review),
     * com eager loading do perfil do aluno e da missão base, ordenado por FIFO (mais antigo primeiro).
     */
        #[OA\Get(
        path: "/api/v1/teacher/classrooms/{id}/pending-evaluations",
        summary: "Obter Detalhes da Turma Escolar",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function pendingEvaluations(Request $request, string $id): JsonResponse
    {
        $teacher = $request->user();

        // Validar acesso à turma
        $classroom = Classroom::where('id', $id)
            ->where(function ($q) use ($teacher) {
                $q->where('tenant_id', $teacher->tenant_id)
                  ->orWhereHas('teachers', fn($t) => $t->where('users.id', $teacher->id));
            })
            ->firstOrFail();

        // Recolhe IDs de todos os alunos da turma
        $teenIds = $classroom->teenStudents()->pluck('users.id');
        $childIds = $classroom->childStudents()->pluck('child_profiles.id');

        $pending = UserProgress::with([
            'learningObject:id,title,type,reward_xp,reward_coins,description',
            'teen:id,name,email',
            'child:id,name,avatar,total_xp',
        ])
            ->where('status', 'pending_review')
            ->where(function ($q) use ($teenIds, $childIds) {
                $q->whereIn('user_id', $teenIds)
                  ->orWhereIn('child_profile_id', $childIds);
            })
            ->orderBy('submitted_at', 'asc') // FIFO: mais antigo primeiro
            ->get()
            ->map(function ($p) {
                $studentName = $p->child?->name ?? $p->teen?->name ?? 'Aluno';
                $studentAvatar = $p->child?->avatar ?? '👤';
                $studentType = $p->user_id ? 'teen' : 'child';
                $studentId = $p->user_id ?? $p->child_profile_id;

                return [
                    'id'                  => $p->id,
                    'progress_id'         => $p->id,
                    'status'              => $p->status,
                    'submission_payload'  => $p->submission_payload,
                    'submission_url'      => $p->submission_url,
                    'submission_note'     => $p->submission_note,
                    'submitted_at'        => $p->submitted_at?->toISOString(),
                    'student'             => [
                        'id'     => $studentId,
                        'name'   => $studentName,
                        'type'   => $studentType,
                        'avatar' => $studentAvatar,
                    ],
                    'mission'             => [
                        'id'           => $p->learningObject?->id,
                        'title'        => $p->learningObject?->title,
                        'type'         => $p->learningObject?->type,
                        'description'  => $p->learningObject?->description,
                        'reward_xp'    => (int) ($p->learningObject?->reward_xp ?? 0),
                        'reward_coins' => (int) ($p->learningObject?->reward_coins ?? 0),
                    ],
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => $pending,
        ], 200);
    }

    /** POST /api/v1/b2b/evaluations/{progressId}/approve */
    public function approve(Request $request, string $progressId): JsonResponse
    {
        $request->merge(['status' => 'approved']);

        return $this->evaluate($request, $progressId);
    }

    /** POST /api/v1/b2b/evaluations/{progressId}/reject */
    public function reject(Request $request, string $progressId): JsonResponse
    {
        $request->merge(['status' => 'rejected']);

        return $this->evaluate($request, $progressId);
    }

    /**
     * POST /api/v1/school/evaluations/{progress_id}
     *
     * Avalia uma missão (Aprovar / Rejeitar) com bloqueio transacional (lockForUpdate)
     * e distribuição segura de moedas/XP via LedgerEntry.
     */
    public function evaluate(Request $request, string $progressId): JsonResponse
    {
        $request->validate([
            'status'           => 'required|in:approved,rejected',
            'teacher_feedback' => 'nullable|string|max:1000',
            'feedback'         => 'nullable|string|max:1000', // alias
        ]);

        $teacher = $request->user();
        $newStatus = $request->status;
        $feedback = $request->teacher_feedback ?? $request->feedback;

        $responsePayload = null;

        DB::transaction(function () use ($progressId, $teacher, $newStatus, $feedback, &$responsePayload) {
            // 1. Bloqueio pessimista para evitar race conditions (duplo clique)
            $progress = UserProgress::with('learningObject')
                ->where('id', $progressId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($progress->status !== 'pending_review') {
                abort(422, 'Esta submissão já foi avaliada anteriormente (estado atual: ' . $progress->status . ').');
            }

            // O professor so avalia alunos das turmas da sua escola (admin avalia qualquer um).
            if ($teacher->role !== 'admin') {
                $inTenant = DB::table('classroom_user')
                    ->join('classrooms', 'classrooms.id', '=', 'classroom_user.classroom_id')
                    ->where('classrooms.tenant_id', $teacher->tenant_id)
                    ->where(function ($q) use ($progress) {
                        $q->when($progress->child_profile_id, fn ($qq) => $qq->orWhere('classroom_user.child_profile_id', $progress->child_profile_id))
                          ->when($progress->user_id, fn ($qq) => $qq->orWhere('classroom_user.user_id', $progress->user_id));
                    })
                    ->exists();

                if (!$inTenant) {
                    abort(403, 'Esta submissão não pertence a uma turma da sua escola.');
                }
            }

            // Identificar o aluno (Teen ou Criança)
            $student = null;
            $ownerType = null;

            if ($progress->user_id) {
                $student = User::find($progress->user_id);
                $ownerType = User::class;
            } else {
                $student = ChildProfile::find($progress->child_profile_id);
                $ownerType = ChildProfile::class;
            }

            $missionTitle = $progress->learningObject?->title ?? 'Missão';

            // =================================================================
            // CENÁRIO A: REJEIÇÃO
            // =================================================================
            if ($newStatus === 'rejected') {
                $progress->update([
                    'status'           => 'rejected',
                    'teacher_feedback' => $feedback,
                    'feedback'         => $feedback,
                    'reviewed_by'      => $teacher->id,
                    'reviewed_at'      => now(),
                    'evaluated_at'     => now(),
                ]);

                // Notificar o aluno para refazer
                if ($student) {
                    $student->notify(new MissionEvaluatedNotification(
                        status: 'rejected',
                        missionTitle: $missionTitle,
                        feedback: $feedback
                    ));
                }

                $responsePayload = [
                    'status'  => 'success',
                    'message' => 'Submissão rejeitada. O aluno foi notificado para rever a tarefa.',
                    'data'    => [
                        'progress_id' => $progress->id,
                        'status'      => 'rejected',
                    ],
                ];

                return;
            }

            // =================================================================
            // CENÁRIO B: APROVAÇÃO
            // =================================================================
            $baseXp = (int) ($progress->learningObject?->reward_xp ?? 0);
            $baseCoins = (int) ($progress->learningObject?->reward_coins ?? 0);

            // 1. Atualizar o registo de progresso
            $progress->update([
                'status'             => 'approved',
                'completed'          => true,
                'completed_at'       => now(),
                'evaluated_at'       => now(),
                'reviewed_at'        => now(),
                'reviewed_by'        => $teacher->id,
                'teacher_feedback'   => $feedback,
                'feedback'           => $feedback,
                'xp_awarded'         => $baseXp,
                'coins_awarded'      => $baseCoins,
                'total_xp_earned'    => $baseXp,
                'total_coins_earned' => $baseCoins,
            ]);

            // 2. Incrementar XP no perfil do aluno
            if ($student instanceof ChildProfile && $baseXp > 0) {
                $student->increment('total_xp', $baseXp);
            }

            // 3. Creditar moedas (KVC) na carteira do aluno através do Ledger append-only
            if ($student && $baseCoins > 0) {
                $wallet = Wallet::firstOrCreate(
                    [
                        'walletable_id'   => $student->id,
                        'walletable_type' => $ownerType,
                        'currency'        => 'KVC',
                    ],
                    [
                        'wallet_type' => 'virtual',
                        'balance'     => 0,
                        'is_frozen'   => false,
                        'tenant_id'   => $student->tenant_id ?? $teacher->tenant_id,
                    ]
                );

                $idempotencyKey = "eval:{$progress->id}:kvc";

                if (!LedgerEntry::where('idempotency_key', $idempotencyKey)->exists()) {
                    LedgerEntry::create([
                        'wallet_id'       => $wallet->id,
                        'idempotency_key' => $idempotencyKey,
                        'direction'       => 'credit',
                        'entry_type'      => 'mission_reward',
                        'amount'          => $baseCoins,
                        'description'     => "Recompensa de Missão: {$missionTitle}",
                        'metadata'        => [
                            'progress_id'        => $progress->id,
                            'learning_object_id' => $progress->learning_object_id,
                            'evaluated_by'       => $teacher->id,
                        ],
                        'created_at'      => now(),
                    ]);

                    $wallet->increment('balance', $baseCoins);
                }
            }

            // 4. Disparar notificação Push / Database ao aluno
            if ($student) {
                $student->notify(new MissionEvaluatedNotification(
                    status: 'approved',
                    missionTitle: $missionTitle,
                    xp: $baseXp,
                    coins: $baseCoins,
                    feedback: $feedback
                ));
            }

            $responsePayload = [
                'status'  => 'success',
                'message' => "Submissão aprovada! Foram creditados +{$baseCoins} KVC e +{$baseXp} XP ao aluno.",
                'data'    => [
                    'progress_id'   => $progress->id,
                    'status'        => 'approved',
                    'xp_awarded'    => $baseXp,
                    'coins_awarded' => $baseCoins,
                ],
            ];
        });

        return response()->json($responsePayload, 200);
    }

    /**
     * POST /api/v1/school/evaluations/{progress_id}/submit
     * 
     * Submissão de prova por parte do aluno.
     */
        #[OA\Post(
        path: "/api/v1/school/evaluations/{progressId}/submit",
        summary: "School Evaluations {progressId} Submit (POST)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function submit(Request $request, string $progressId): JsonResponse
    {
        $request->validate([
            'submission_payload' => 'nullable|array',
            'submission_url'     => 'nullable|url|max:2048',
            'submission_note'    => 'nullable|string|max:1000',
        ]);

        $progress = UserProgress::findOrFail($progressId);

        $progress->update([
            'status'             => 'pending_review',
            'submission_payload' => $request->submission_payload,
            'submission_url'     => $request->submission_url,
            'submission_note'    => $request->submission_note,
            'submitted_at'       => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Prova submetida com sucesso. Aguarda aprovação do professor.',
            'data'    => [
                'progress_id' => $progress->id,
                'status'      => 'pending_review',
            ],
        ], 200);
    }
}
