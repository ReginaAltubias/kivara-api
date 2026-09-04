<?php

namespace App\Http\Controllers\Api\ParentGate;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ChildProfile;
use App\Models\Classroom;
use App\Models\ClassroomUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ChildClassroomController extends Controller
{
        #[OA\Post(
        path: "/api/v1/parents/child-profiles/{id}/join-classroom",
        summary: "Criar Nova Turma Escolar",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function joinClassroom(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'invite_code' => 'required|string|max:10',
        ]);

        $parent = $request->user();

        // Verificar se a criança pertence ao pai
        $child = ChildProfile::where('user_id', $parent->id)->findOrFail($id);

        // Procurar a turma pelo código
        $classroom = Classroom::where('invite_code', $request->invite_code)
            ->where('is_active', true)
            ->first();

        if (!$classroom) {
            return response()->json([
                'status' => 'error',
                'message' => 'Código de turma inválido ou turma inativa.',
            ], 404);
        }

        DB::transaction(function () use ($child, $classroom) {
            // Atualizar o tenant_id da criança para refletir a escola
            $child->tenant_id = $classroom->tenant_id;
            $child->save();

            // Evitar duplicados
            $existing = ClassroomUser::where('classroom_id', $classroom->id)
                ->where('child_profile_id', $child->id)
                ->where('role', 'student')
                ->first();

            if (!$existing) {
                ClassroomUser::create([
                    'classroom_id' => $classroom->id,
                    'child_profile_id' => $child->id,
                    'role' => 'student',
                ]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Criança adicionada à turma com sucesso.',
            'data' => [
                'classroom_name' => $classroom->name,
                'school_id' => $classroom->tenant_id,
            ],
        ], 200);
    }
}
