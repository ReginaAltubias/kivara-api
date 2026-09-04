<?php

namespace App\Http\Controllers\Api\Admin;

use OpenApi\Attributes as OA;

use App\Enums\ContentStatus;
use App\Enums\LessonContentType;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminLessonController extends Controller
{
    /**
     * Lista lições (opcionalmente filtradas por trilho).
     */
        #[OA\Get(
        path: "/api/v1/admin/lessons",
        summary: "Listar Lições Educativas",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Lesson::query()->with('trail')->orderBy('order');

        if ($request->filled('trail_id')) {
            $query->where('trail_id', $request->string('trail_id'));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    /**
     * Cria uma lição num trilho.
     */
        #[OA\Post(
        path: "/api/v1/admin/lessons",
        summary: "Criar Nova Lição Educativa",
        tags: ["Administração Central"],
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
        try {
            $validated = $request->validate([
                'trail_id' => 'required|uuid|exists:trails,id',
                'title' => 'required|string|max:255',
                'content_type' => ['required', Rule::enum(LessonContentType::class)],
                'content_body' => 'required|string',
                'media_url' => 'nullable|string|max:2048',
                'xp_reward' => 'nullable|integer|min:0',
                'status' => ['nullable', Rule::enum(ContentStatus::class)],
                'order' => 'nullable|integer|min:0',
            ]);

            if (! array_key_exists('order', $validated) || $validated['order'] === null) {
                $maxOrder = Lesson::where('trail_id', $validated['trail_id'])->max('order');
                $validated['order'] = ((int) $maxOrder) + 1;
            }

            $validated['status'] = $validated['status'] ?? ContentStatus::DRAFT->value;
            $validated['xp_reward'] = $validated['xp_reward'] ?? 0;

            $lesson = Lesson::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Lição criada com sucesso.',
                'data' => $lesson->load('trail'),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dados de validação inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não foi possível criar a lição.',
            ], 500);
        }
    }

    /**
     * Detalhe de uma lição.
     */
        #[OA\Get(
        path: "/api/v1/admin/lessons/{lesson}",
        summary: "Obter Detalhes da Lição Educativa",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(string $lesson): JsonResponse
    {
        $model = Lesson::with(['trail', 'missionTemplates'])->findOrFail($lesson);

        return response()->json([
            'status' => 'success',
            'data' => $model,
        ]);
    }

    /**
     * Atualiza uma lição.
     */
        #[OA\Patch(
        path: "/api/v1/admin/lessons/{lesson}",
        summary: "Atualizar Lição Educativa",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function update(Request $request, string $lesson): JsonResponse
    {
        try {
            $model = Lesson::findOrFail($lesson);

            $validated = $request->validate([
                'trail_id' => 'sometimes|required|uuid|exists:trails,id',
                'title' => 'sometimes|required|string|max:255',
                'content_type' => ['sometimes', 'required', Rule::enum(LessonContentType::class)],
                'content_body' => 'sometimes|required|string',
                'media_url' => 'nullable|string|max:2048',
                'xp_reward' => 'nullable|integer|min:0',
                'status' => ['nullable', Rule::enum(ContentStatus::class)],
                'order' => 'nullable|integer|min:0',
            ]);

            $model->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Lição atualizada com sucesso.',
                'data' => $model->fresh()->load('trail'),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dados de validação inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não foi possível atualizar a lição.',
            ], 500);
        }
    }

    /**
     * Soft-delete de uma lição.
     */
        #[OA\Delete(
        path: "/api/v1/admin/lessons/{lesson}",
        summary: "Admin Lessons {lesson} (DELETE)",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 204, description: "Recurso removido com sucesso.")
        ]
    )]
    public function destroy(string $lesson): JsonResponse
    {
        $model = Lesson::findOrFail($lesson);
        $model->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Lição removida com sucesso.',
        ]);
    }

    /**
     * Reordenação em massa (drag-and-drop no Admin).
     *
     * Payload esperado:
     * {
     *   "trail_id": "uuid",
     *   "lessons": [{ "id": "uuid-1", "order": 1 }, ...]
     * }
     */
        #[OA\Post(
        path: "/api/v1/admin/lessons/reorder",
        summary: "Criar Nova Lição Educativa",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function reorder(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'trail_id' => 'required|uuid|exists:trails,id',
                'lessons' => 'required|array|min:1',
                'lessons.*.id' => 'required|uuid|exists:lessons,id',
                'lessons.*.order' => 'required|integer|min:0',
            ]);

            $lessonIds = collect($validated['lessons'])->pluck('id')->unique()->values();

            $lessons = Lesson::query()
                ->whereIn('id', $lessonIds)
                ->get(['id', 'trail_id']);

            if ($lessons->count() !== $lessonIds->count()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Uma ou mais lições não foram encontradas.',
                ], 404);
            }

            $foreignTrail = $lessons->first(fn (Lesson $lesson) => $lesson->trail_id !== $validated['trail_id']);

            if ($foreignTrail !== null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Todas as lições devem pertencer ao mesmo trilho indicado em trail_id.',
                    'error_code' => 'mixed_trails',
                ], 422);
            }

            DB::transaction(function () use ($validated) {
                foreach ($validated['lessons'] as $item) {
                    Lesson::where('id', $item['id'])
                        ->where('trail_id', $validated['trail_id'])
                        ->update(['order' => $item['order']]);
                }
            });

            $ordered = Lesson::query()
                ->where('trail_id', $validated['trail_id'])
                ->orderBy('order')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Ordem das lições atualizada com sucesso.',
                'data' => $ordered,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dados de validação inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não foi possível reordenar as lições.',
            ], 500);
        }
    }
}
