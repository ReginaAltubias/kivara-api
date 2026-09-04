<?php

namespace App\Http\Controllers\Api\ParentGate;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\LedgerEntry;
use App\Models\Task;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    /**
     * Lista tarefas atribuídas aos filhos do encarregado autenticado.
     */
        #[OA\Get(
        path: "/api/v1/tasks/household",
        summary: "Listar Tarefas Familiares",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Task::query()
            ->whereHas('child', fn ($q) => $q->where('parent_id', $user->id))
            ->with(['child:id,name,avatar,parent_id'])
            ->orderByDesc('created_at');

        if ($request->filled('child_id')) {
            $query->where('child_id', $request->string('child_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $tasks = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $tasks,
        ], 200);
    }

    /**
     * Cria uma nova tarefa para a criança.
     */
        #[OA\Post(
        path: "/api/v1/tasks",
        summary: "Criar Nova Tarefa com Recompensa",
        tags: ["Família, Mesadas & Parent Gate"],
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
        $user = $request->user();

        $validated = $request->validate([
            'child_id' => [
                'required',
                'uuid',
                'exists:child_profiles,id',
                function ($attribute, $value, $fail) use ($user) {
                    $exists = ChildProfile::where('id', $value)->where('parent_id', $user->id)->exists();
                    if (!$exists) {
                        $fail('A criança selecionada não pertence ao seu agregado familiar.');
                    }
                },
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'reward_amount' => 'required|numeric|min:0',
            'due_date' => 'nullable|date',
            'is_recurring' => 'nullable|boolean',
            'recurrence_pattern' => 'nullable|string|in:daily,weekly,monthly',
        ]);

        $task = Task::create([
            'child_id' => $validated['child_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'reward_amount' => $validated['reward_amount'],
            'due_date' => $validated['due_date'] ?? null,
            'is_recurring' => $validated['is_recurring'] ?? false,
            'recurrence_pattern' => $validated['recurrence_pattern'] ?? null,
            'status' => 'pending',
        ]);

        $task->load('child:id,name,avatar');

        return response()->json([
            'status' => 'success',
            'message' => 'Tarefa criada com sucesso.',
            'data' => $task,
        ], 201);
    }

    /**
     * Atualiza uma tarefa existente.
     */
        #[OA\Patch(
        path: "/api/v1/tasks/{id}",
        summary: "Editar Tarefa Familiar",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $task = Task::whereHas('child', fn ($q) => $q->where('parent_id', $user->id))->findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'reward_amount' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'is_recurring' => 'nullable|boolean',
            'recurrence_pattern' => 'nullable|string|in:daily,weekly,monthly',
            'status' => 'nullable|string|in:pending,in_progress,completed,rejected',
        ]);

        $task->update($validated);
        $task->load('child:id,name,avatar');

        return response()->json([
            'status' => 'success',
            'message' => 'Tarefa atualizada com sucesso.',
            'data' => $task,
        ], 200);
    }

    /**
     * Elimina uma tarefa.
     */
        #[OA\Delete(
        path: "/api/v1/tasks/{id}",
        summary: "Tasks {id} (DELETE)",
        tags: ["Família, Mesadas & Parent Gate"],
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
        $user = $request->user();

        $task = Task::whereHas('child', fn ($q) => $q->where('parent_id', $user->id))->findOrFail($id);
        $task->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tarefa eliminada com sucesso.',
        ], 200);
    }

    /**
     * Aprova a conclusão da tarefa e liberta a recompensa atomicamente.
     */
        #[OA\Post(
        path: "/api/v1/tasks/{id}/approve",
        summary: "Aprovar Tarefa e Liberar Recompensa Financeira",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function approve(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $task = Task::whereHas('child', fn ($q) => $q->where('parent_id', $user->id))->findOrFail($id);

        if ($task->status === 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Esta tarefa já se encontra aprovada e recompensada.',
            ], 422);
        }

        $result = DB::transaction(function () use ($task, $user) {
            $child = ChildProfile::lockForUpdate()->findOrFail($task->child_id);

            // Obter ou criar carteira virtual KVC da criança
            $childWallet = Wallet::where('walletable_id', $child->id)
                ->where('walletable_type', ChildProfile::class)
                ->lockForUpdate()
                ->first();

            if (!$childWallet) {
                $childWallet = Wallet::create([
                    'walletable_id' => $child->id,
                    'walletable_type' => ChildProfile::class,
                    'tenant_id' => $child->tenant_id ?: ($user->tenant_id ?? '00000000-0000-0000-0000-000000000000'),
                    'currency' => 'KVC',
                    'balance' => 0,
                    'is_frozen' => false,
                ]);
            }

            $rewardAmount = (float) $task->reward_amount;

            if ($rewardAmount > 0) {
                $idempotencyKey = 'TASK-REW-' . $task->id . '-' . Str::random(8);

                // Registo imutável no livro-razão (LedgerEntry)
                LedgerEntry::create([
                    'wallet_id' => $childWallet->id,
                    'idempotency_key' => $idempotencyKey,
                    'direction' => 'credit',
                    'entry_type' => 'task_reward',
                    'amount' => $rewardAmount,
                    'description' => 'Recompensa da tarefa: ' . $task->title,
                    'metadata' => [
                        'task_id' => $task->id,
                        'task_title' => $task->title,
                        'approved_by' => $user->id,
                        'parent_name' => $user->name,
                    ],
                ]);

                // Atualizar saldo da carteira da criança
                $childWallet->balance = (float) $childWallet->balance + $rewardAmount;
                $childWallet->save();

                // Incremento de XP pedagógico (2x moedas como XP)
                $child->total_xp = (int) ($child->total_xp ?? 0) + (int) round($rewardAmount * 2);
                $child->save();
            }

            $task->update([
                'status' => 'aprovada',
            ]);

            return [
                'id' => $task->id,
                'status' => 'aprovada',
                'reward_credited' => $rewardAmount,
                'approved_at' => now()->toIso8601String(),
                'task' => $task->fresh(['child:id,name,avatar']),
                'new_balance' => (float) $childWallet->balance,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $result['id'],
                'status' => 'aprovada',
                'reward_credited' => $result['reward_credited'],
                'approved_at' => $result['approved_at'],
                'task' => $result['task'],
            ],
            'wallet' => [
                'balance' => $result['new_balance'],
                'currency' => 'KVC',
            ],
        ], 200);
    }

    /**
     * Rejeita a conclusão da tarefa.
     */
        #[OA\Post(
        path: "/api/v1/tasks/{id}/reject",
        summary: "Rejeitar Conclusão da Tarefa",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function reject(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $task = Task::whereHas('child', fn ($q) => $q->where('parent_id', $user->id))->findOrFail($id);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $task->update([
            'status' => 'rejected',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tarefa rejeitada.',
            'data' => $task->fresh(['child:id,name,avatar']),
        ], 200);
    }

    /**
     * Retorna sugestões inteligentes de tarefas por faixa etária e dificuldade.
     */
        #[OA\Post(
        path: "/api/v1/tasks/suggest",
        summary: "Criar Nova Tarefa com Recompensa",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function suggest(Request $request): JsonResponse
    {
        $suggestions = [
            [
                'title' => 'Arrumar o quarto e fazer a cama',
                'description' => 'Deixar o quarto limpo e a cama bem esticada.',
                'reward' => 50,
                'difficulty' => 'easy',
                'category' => 'household',
                'icon' => '🛏️',
            ],
            [
                'title' => 'Lavar a louça do almoço ou jantar',
                'description' => 'Ajudar na cozinha lavando e secando a louça da refeição.',
                'reward' => 100,
                'difficulty' => 'medium',
                'category' => 'household',
                'icon' => '🍽️',
            ],
            [
                'title' => 'Leitura diária de 20 minutos',
                'description' => 'Ler um capítulo de um livro à escolha e contar aos pais o que aprendeu.',
                'reward' => 80,
                'difficulty' => 'easy',
                'category' => 'learning',
                'icon' => '📖',
            ],
            [
                'title' => 'Organizar a mochila e deveres da escola',
                'description' => 'Preparar todo o material escolar para o dia seguinte sem esquecer nada.',
                'reward' => 60,
                'difficulty' => 'easy',
                'category' => 'school',
                'icon' => '🎒',
            ],
            [
                'title' => 'Poupar moedas no Cofre de Sonhos',
                'description' => 'Fazer um depósito pedagógico no cofre de metas financeiras.',
                'reward' => 75,
                'difficulty' => 'medium',
                'category' => 'finance',
                'icon' => '🏦',
            ],
            [
                'title' => 'Ajudar a regar as plantas e cuidar do jardim',
                'description' => 'Regar as plantas de casa com cuidado e responsabilidade.',
                'reward' => 50,
                'difficulty' => 'easy',
                'category' => 'nature',
                'icon' => '🌱',
            ],
        ];

        return response()->json([
            'status' => 'success',
            'suggestions' => $suggestions,
        ], 200);
    }
}
