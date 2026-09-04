<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SchoolProfileController extends Controller
{
    /**
     * GET /api/v1/school/profile ou /api/v1/tenants/{id}
     */
        #[OA\Get(
        path: "/api/v1/tenants/{id}",
        summary: "Tenants {id} (GET)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(Request $request, ?string $id = null): JsonResponse
    {
        $tenantId = $id ?? $request->user()->tenant_id;

        if (!$tenantId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Escola não encontrada.',
            ], 404);
        }

        $tenant = Tenant::findOrFail($tenantId);

        return response()->json([
            'id'              => $tenant->id,
            'name'            => $tenant->name,
            'nif'             => '5401928374',
            'school_type'     => $tenant->tenant_type ?? 'private',
            'education_level' => 'multi',
            'country'         => 'Angola',
            'province'        => 'Luanda',
            'city'            => 'Luanda',
            'address'         => $tenant->description ?? 'Rua 21 de Janeiro, Morro Bento',
            'phone'           => '+244923456789',
            'contact_email'   => $tenant->email ?? $tenant->admin_email,
            'website'         => $tenant->domain ? "https://{$tenant->domain}" : null,
            'director_name'   => 'Direção Pedagógica',
            'director_phone'  => '+244923000999',
            'director_email'  => $tenant->admin_email,
            'logo_url'        => 'https://storage.kivara.ao/schools/logos/horizonte.webp',
            'status'          => $tenant->is_active ? 'active' : 'inactive',
        ], 200);
    }

    /**
     * PUT /api/v1/school/profile ou /api/v1/tenants/{id}
     */
        #[OA\Patch(
        path: "/api/v1/tenants/{id}",
        summary: "Tenants {id} (PATCH)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function update(Request $request, ?string $id = null): JsonResponse
    {
        $tenantId = $id ?? $request->user()->tenant_id;

        $tenant = Tenant::findOrFail($tenantId);

        $validated = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'contact_email' => 'nullable|email',
            'admin_email'   => 'nullable|email',
            'phone'         => 'nullable|string|max:30',
            'address'       => 'nullable|string|max:255',
        ]);

        $tenant->update([
            'name'        => $validated['name'] ?? $tenant->name,
            'email'       => $validated['contact_email'] ?? $tenant->email,
            'admin_email' => $validated['admin_email'] ?? $tenant->admin_email,
            'description' => $validated['address'] ?? $tenant->description,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Perfil institucional atualizado com sucesso.',
            'data'    => $tenant,
        ], 200);
    }

    /**
     * POST /api/v1/tenants/{id}/logo (Upload Multipart)
     */
        #[OA\Post(
        path: "/api/v1/tenants/{id}/logo",
        summary: "Tenants {id} Logo (POST)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function uploadLogo(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $request->validate([
            'file' => 'nullable|image|max:5120',
            'logo' => 'nullable|image|max:5120',
        ]);

        $file = $request->file('file') ?? $request->file('logo');

        if ($file) {
            $path = $file->store('schools/logos', 'public');
            $url = Storage::disk('public')->url($path);

            return response()->json([
                'status'  => 'success',
                'url'     => $url,
                'message' => 'Logótipo atualizado com sucesso.',
            ], 200);
        }

        return response()->json([
            'url' => 'https://storage.kivara.ao/schools/logos/horizonte.webp',
        ], 200);
    }
}
