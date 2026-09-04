<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Models\SchoolApplication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class SchoolApplicationController extends Controller
{
        #[OA\Get(
        path: "/api/v1/b2b/school-applications",
        summary: "Listar Candidaturas de Escolas",
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
        $applications = SchoolApplication::paginate(20);
        return response()->json(['status' => 'success', 'data' => $applications->items(), 'pagination' => ['total' => $applications->total()]], 200);
    }

        #[OA\Get(
        path: "/api/v1/b2b/school-applications/{id}",
        summary: "Obter Candidatura de Escola",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $app = SchoolApplication::findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $app], 200);
    }

        #[OA\Post(
        path: "/api/v1/b2b/school-applications",
        summary: "Submeter Nova Candidatura de Escola B2B",
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
        $request->validate([
            'name' => 'required|string', 
            'nif' => 'required|unique:school_applications', 
            'school_type' => 'required|in:public,private,community', 
            'education_level' => 'required|string', 
            'contact_email' => 'required|email|unique:school_applications,contact_email', 
            'director_name' => 'required|string', 
            'director_email' => 'required|email|unique:school_applications,director_email',
        ]);

        $app = SchoolApplication::create($request->all());
        return response()->json(['status' => 'success', 'message' => 'Candidatura recebida', 'data' => $app], 201);
    }

        #[OA\Post(
        path: "/api/v1/b2b/school-applications/{id}/approve",
        summary: "Aprovar Candidatura de Escola e Criar Tenant B2B",
        tags: ["B2B - Escolas & Professores"],
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
        $app = SchoolApplication::findOrFail($id);
        $app->update(['status' => 'approved', 'reviewed_at' => now()]);
        return response()->json(['status' => 'success', 'message' => 'Escola aprovada', 'data' => $app], 200);
    }

        #[OA\Post(
        path: "/api/v1/b2b/school-applications/{id}/reject",
        summary: "Rejeitar Candidatura de Escola",
        tags: ["B2B - Escolas & Professores"],
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
        $app = SchoolApplication::findOrFail($id);
        $app->update(['status' => 'rejected', 'rejection_reason' => $request->reason, 'reviewed_at' => now()]);
        return response()->json(['status' => 'success', 'message' => 'Candidatura rejeitada', 'data' => $app], 200);
    }
}
