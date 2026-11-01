<?php

namespace App\Http\Controllers\Api\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Trail;
use App\Services\PedagogicalOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TrailController extends Controller
{
    public function __construct(
        protected ?PedagogicalOrchestratorService $pedagogicalService = null
    ) {
        $this->pedagogicalService = $pedagogicalService ?? app(PedagogicalOrchestratorService::class);
    }

    /**
     * GET /api/v1/trails - Listar Trilhas
     */
        #[OA\Get(
        path: "/api/v1/trails",
        summary: "Listar Trilhas Educativas",
        tags: ["Motor Educacional & RPG"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Trail::with('lessons');

        if ($request->filled('grade_level')) {
            $query->where('grade_level', $request->grade_level);
        }

        if ($request->has('is_published')) {
            $query->where('is_published', filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->get('per_page', 15);
        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(function ($t) {
            return [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'grade_level' => $t->grade_level ?? '5-7',
                'is_mandatory' => (bool) ($t->is_mandatory ?? false),
                'is_published' => (bool) ($t->is_published ?? false),
                'version' => (int) ($t->version ?? 1),
                'lesson_ids' => $t->lessons->pluck('id')->toArray(),
                'created_at' => $t->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    /**
     * POST /api/v1/trails - Criar Trilha
     */
        #[OA\Post(
        path: "/api/v1/trails",
        summary: "Criar Nova Trilha Educativa",
        tags: ["Motor Educacional & RPG"],
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
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'grade_level' => 'nullable|string',
            'kivara_phase_id' => 'nullable|integer|min:1|max:10',
            'is_mandatory' => 'nullable|boolean',
            'lesson_ids' => 'nullable|array',
            'lesson_ids.*' => 'uuid|exists:lessons,id',
        ]);

        $trail = Trail::create([
            'id' => (string) Str::uuid(),
            'title' => $request->title,
            'description' => $request->description,
            'kivara_phase_id' => $request->kivara_phase_id ?? 1,
            'grade_level' => $request->grade_level ?? '5-7',
            'is_mandatory' => (bool) ($request->is_mandatory ?? false),
            'is_published' => false,
            'version' => 1,
            'order' => (int) Trail::max('order') + 1,
        ]);

        if ($request->filled('lesson_ids')) {
            Lesson::whereIn('id', $request->lesson_ids)->update(['trail_id' => $trail->id]);
        }

        return response()->json([
            'data' => [
                'id' => $trail->id,
                'title' => $trail->title,
                'description' => $trail->description,
                'grade_level' => $trail->grade_level,
                'is_mandatory' => $trail->is_mandatory,
                'is_published' => $trail->is_published,
                'version' => $trail->version,
                'lesson_ids' => $request->lesson_ids ?? [],
                'created_at' => $trail->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * PATCH /api/v1/trails/{id} - Atualizar Trilha (Bloqueado se publicada)
     */
        #[OA\Patch(
        path: "/api/v1/trails/{id}",
        summary: "Trails {id} (PATCH)",
        tags: ["Motor Educacional & RPG"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $trail = Trail::findOrFail($id);

        if ($trail->is_published) {
            return response()->json([
                'status' => 'error',
                'message' => 'Trilhas publicadas são imutáveis. Utilize POST /trails/{id}/version para criar uma nova versão.',
            ], 422);
        }

        $trail->update($request->only(['title', 'description', 'grade_level', 'is_mandatory']));

        if ($request->has('lesson_ids')) {
            Lesson::where('trail_id', $trail->id)->update(['trail_id' => null]);
            Lesson::whereIn('id', $request->lesson_ids)->update(['trail_id' => $trail->id]);
        }

        return response()->json([
            'data' => $trail->fresh()->load('lessons'),
        ], 200);
    }

    /**
     * POST /api/v1/trails/{id}/publish - Publicar Trilha
     */
        #[OA\Post(
        path: "/api/v1/trails/{id}/publish",
        summary: "Criar Nova Trilha Educativa",
        tags: ["Motor Educacional & RPG"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function publish(Request $request, string $id): JsonResponse
    {
        $trail = Trail::with('lessons.missionTemplates')->findOrFail($id);

        try {
            $published = $this->pedagogicalService->publishTrail($trail);

            return response()->json([
                'status' => 'success',
                'message' => 'Trilha publicada com sucesso e bloqueada para mutações diretas.',
                'data' => $published,
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/trails/{id}/version - Criar Nova Versão Imutável
     */
        #[OA\Post(
        path: "/api/v1/trails/{id}/version",
        summary: "Criar Nova Trilha Educativa",
        tags: ["Motor Educacional & RPG"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function version(Request $request, string $id): JsonResponse
    {
        $trail = Trail::with('lessons')->findOrFail($id);

        $newVersion = $this->pedagogicalService->createNewTrailVersion($trail);

        return response()->json([
            'status' => 'success',
            'message' => "Versão {$newVersion->version} criada com sucesso para edição.",
            'data' => $newVersion,
        ], 201);
    }

    /**
     * POST /api/v1/lessons/{id}/submit-review
     */
        #[OA\Post(
        path: "/api/v1/lessons/{id}/submit-review",
        summary: "Criar Nova Lição Educativa",
        tags: ["Motor Educacional & RPG"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function submitLessonReview(Request $request, string $id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);
        $lesson->status = 'pending_review';
        $lesson->submitted_at = now();
        $lesson->save();

        return response()->json([
            'data' => [
                'id' => $lesson->id,
                'status' => 'pending_review',
                'submitted_at' => $lesson->submitted_at->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * POST /api/v1/lessons/{id}/approve
     */
        #[OA\Post(
        path: "/api/v1/lessons/{id}/approve",
        summary: "Criar Nova Lição Educativa",
        tags: ["Motor Educacional & RPG"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function approveLesson(Request $request, string $id): JsonResponse
    {
        $lesson = Lesson::with('missionTemplates')->findOrFail($id);

        if (!$this->pedagogicalService->validateLoop1Requirement($lesson)) {
            return response()->json([
                'status' => 'error',
                'message' => 'A lição não pode ser aprovada sem possuir uma missão prática associada (Loop 1).',
            ], 422);
        }

        $lesson->status = 'published';
        $lesson->approved_at = now();
        $lesson->approved_by = $request->user()->id ?? (string) Str::uuid();
        $lesson->save();

        return response()->json([
            'data' => [
                'id' => $lesson->id,
                'status' => 'published',
                'approved_at' => $lesson->approved_at->toIso8601String(),
                'approved_by' => $lesson->approved_by,
            ],
        ], 200);
    }

    /** GET /lessons — catalogo de licoes (publicadas; o admin pode pedir todas com include_inactive=1). */
    public function catalog(Request $request): JsonResponse
    {
        $all = $request->boolean('include_inactive') && in_array($request->user()->role, ['admin', 'teacher', 'coordinator', 'coordenador'], true);

        $lessons = \App\Models\Lesson::query()
            ->with('trail:id,title,kivara_phase_id,grade_level')
            ->when(! $all, fn ($q) => $q->where('status', 'published'))
            ->orderBy('trail_id')->orderBy('order')
            ->get();

        return response()->json(['status' => 'success', 'data' => $lessons->map(fn ($l) => $this->presentLesson($l))->values()]);
    }

    /** GET /trails/{id}/lessons */
    public function trailLessons(Request $request, string $id): JsonResponse
    {
        $lessons = \App\Models\Lesson::query()
            ->with('trail:id,title,kivara_phase_id,grade_level')
            ->where('trail_id', $id)
            ->where('status', 'published')
            ->orderBy('order')->get();

        return response()->json(['status' => 'success', 'data' => $lessons->map(fn ($l) => $this->presentLesson($l))->values()]);
    }

    private function presentLesson(\App\Models\Lesson $l): array
    {
        $status = $l->status instanceof \BackedEnum ? $l->status->value : (string) $l->status;

        return [
            'id' => $l->id,
            'title' => $l->title,
            'description' => \Illuminate\Support\Str::limit(trim(strip_tags((string) $l->content_body)), 160),
            'icon' => null,
            'category' => 'saving',
            'difficulty' => null,
            'estimated_minutes' => null,
            'kiva_points_reward' => (int) $l->xp_reward,
            'blocks' => [],
            'quiz' => [],
            'sort_order' => $l->order,
            'is_active' => $status === 'published',
            'status' => $status,
            'trail_ref' => $l->trail_id,
            'trail_name' => $l->trail?->title,
            'mission_template_id' => \App\Models\MissionTemplate::where('lesson_id', $l->id)->value('id'),
        ];
    }
}
