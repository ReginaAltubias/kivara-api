<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\Classroom;
use App\Models\ClassroomUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolStudentController extends Controller
{
    /**
     * GET /api/v1/school/students
     * 
     * Lista todos os alunos vinculados à escola, com informações do encarregado de educação e turma.
     */
        #[OA\Get(
        path: "/api/v1/school/students",
        summary: "School Students (GET)",
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
        $tenantId = $request->user()->tenant_id;

        if (!$tenantId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Utilizador não associado a uma instituição escolar.',
            ], 403);
        }

        $search = $request->query('search');
        $classroomId = $request->query('classroom_id');
        $perPage = (int) ($request->query('per_page', 15));

        // Consulta de alunos (crianças - child_profiles)
        $childQuery = ChildProfile::with(['parent', 'family'])
            ->where('tenant_id', $tenantId);

        if ($search) {
            $childQuery->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('display_name', 'ILIKE', "%{$search}%")
                  ->orWhere('username', 'ILIKE', "%{$search}%");
            });
        }

        if ($classroomId) {
            $childQuery->whereHas('classrooms', function ($q) use ($classroomId) {
                $q->where('classrooms.id', $classroomId);
            });
        }

        $children = $childQuery->paginate($perPage);

        $data = collect($children->items())->map(function ($child) {
            // Localizar turma atual
            $classroomUser = ClassroomUser::with('classroom')
                ->where('child_profile_id', $child->id)
                ->where('role', 'student')
                ->first();

            $guardian = $child->parent;

            return [
                'id'                 => $child->id,
                'student_profile_id' => $child->id,
                'child_id'           => $child->id,
                'display_name'       => $child->display_name ?? $child->name,
                'username'           => $child->username,
                'avatar'             => $child->avatar ?? '🦁',
                'age'                => $child->date_of_birth ? $child->date_of_birth->age : 10,
                'date_of_birth'      => $child->date_of_birth?->toDateString(),
                'gender'             => 'other',
                'phone'              => null,
                'classroom_id'       => $classroomUser?->classroom_id,
                'classroom_name'     => $classroomUser?->classroom?->name ?? 'Sem Turma',
                'parent_profile_id'  => $child->parent_id,
                'guardian'           => $guardian ? [
                    'id'           => $guardian->id,
                    'display_name' => $guardian->name,
                    'avatar'       => '👨💼',
                    'phone'        => null,
                    'user'         => [
                        'email' => $guardian->email,
                    ],
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $data,
            'meta'   => [
                'current_page' => $children->currentPage(),
                'last_page'    => $children->lastPage(),
                'per_page'     => $children->perPage(),
                'total'        => $children->total(),
            ],
        ], 200);
    }

    /**
     * POST /api/v1/classrooms/{classroomId}/students/{profileId}
     * 
     * Associa um aluno a uma turma da escola.
     */
        #[OA\Post(
        path: "/api/v1/classrooms/{classroomId}/students/{profileId}",
        summary: "Adicionar Alunos à Turma",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function assignToClassroom(Request $request, string $classroomId, string $profileId): JsonResponse
    {
        $teacher = $request->user();
        $classroom = Classroom::where('tenant_id', $teacher->tenant_id)->findOrFail($classroomId);

        DB::transaction(function () use ($classroom, $profileId) {
            $isUser = User::where('id', $profileId)->exists();

            if ($isUser) {
                ClassroomUser::firstOrCreate([
                    'classroom_id' => $classroom->id,
                    'user_id'      => $profileId,
                    'role'         => 'student',
                ]);
            } else {
                ClassroomUser::firstOrCreate([
                    'classroom_id'     => $classroom->id,
                    'child_profile_id' => $profileId,
                    'role'             => 'student',
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Aluno adicionado à turma com sucesso.',
        ], 200);
    }

    /**
     * DELETE /api/v1/classrooms/{classroomId}/students/{profileId}
     * 
     * Desvincula um aluno de uma turma.
     */
        #[OA\Delete(
        path: "/api/v1/classrooms/{classroomId}/students/{profileId}",
        summary: "Classrooms {classroomId} Students {profileId} (DELETE)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 204, description: "Recurso removido com sucesso.")
        ]
    )]
    public function removeFromClassroom(Request $request, string $classroomId, string $profileId): JsonResponse
    {
        $teacher = $request->user();
        $classroom = Classroom::where('tenant_id', $teacher->tenant_id)->findOrFail($classroomId);

        ClassroomUser::where('classroom_id', $classroom->id)
            ->where(function ($q) use ($profileId) {
                $q->where('child_profile_id', $profileId)
                  ->orWhere('user_id', $profileId);
            })
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Aluno removido da turma.',
        ], 200);
    }

    /**
     * DELETE /api/v1/school/students/{id}
     * 
     * Desassocia o aluno da instituição escolar.
     */
        #[OA\Delete(
        path: "/api/v1/school/students/{id}",
        summary: "School Students {id} (DELETE)",
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
        $tenantId = $request->user()->tenant_id;

        $child = ChildProfile::where('tenant_id', $tenantId)->find($id);

        if ($child) {
            $child->update(['tenant_id' => null]);
            ClassroomUser::where('child_profile_id', $child->id)->delete();
        } else {
            $teen = User::where('tenant_id', $tenantId)->find($id);
            if ($teen) {
                $teen->update(['tenant_id' => null]);
                ClassroomUser::where('user_id', $teen->id)->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Aluno desvinculado da escola.',
        ], 200);
    }
}
