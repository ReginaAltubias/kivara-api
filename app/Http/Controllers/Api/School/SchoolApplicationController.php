<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Models\SchoolApplication;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AccountProvisioner;
use Illuminate\Support\Facades\DB;
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
        $applications = SchoolApplication::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(100);
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

        $app = SchoolApplication::create($request->only([
            'name', 'nif', 'school_type', 'education_level', 'country', 'province', 'city',
            'address', 'phone', 'contact_email', 'website', 'director_name', 'director_phone',
            'director_email', 'motivation',
        ]));
        return response()->json(['status' => 'success', 'message' => 'Candidatura recebida', 'data' => $app->fresh()], 201);
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

        if ($app->status === 'approved' && $app->tenant_id) {
            return response()->json(['status' => 'success', 'message' => 'Candidatura ja aprovada.', 'data' => $app], 200);
        }
        if (Tenant::where('name', $app->name)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Ja existe um tenant com este nome.'], 422);
        }
        if (User::where('email', $app->contact_email)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'O email de contacto ja tem conta.'], 422);
        }

        $emailSent = false;
        DB::transaction(function () use ($app, $request, &$emailSent) {
            $tenant = Tenant::create([
                'name' => $app->name,
                'tenant_type' => 'school',
                'nif' => $app->nif,
                'school_type' => $app->school_type,
                'education_level' => $app->education_level,
                'country' => $app->country,
                'province' => $app->province,
                'city' => $app->city,
                'address' => $app->address,
                'phone' => $app->phone,
                'email' => $app->contact_email,
                'admin_email' => $app->contact_email,
                'website' => $app->website,
                'director_name' => $app->director_name,
                'director_phone' => $app->director_phone,
                'director_email' => $app->director_email,
            ]);

            $result = AccountProvisioner::create([
                'tenant_id' => $tenant->id,
                'name' => $app->director_name ?? $app->name,
                'email' => $app->contact_email,
                'role' => 'coordinator',
            ]);
            $emailSent = $result['email_sent'];

            $app->update([
                'status' => 'approved',
                'tenant_id' => $tenant->id,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()?->id,
            ]);
        });

        return response()->json(['status' => 'success', 'message' => 'Escola aprovada', 'email_sent' => $emailSent, 'data' => $app->fresh()], 200);
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
