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
        $tenant = $this->resolveTenant($request, $id);

        return response()->json([
            'id'              => $tenant->id,
            'name'            => $tenant->name,
            'nif'             => $tenant->nif,
            'school_type'     => $tenant->school_type,
            'tenant_type'     => $tenant->tenant_type,
            'education_level' => $tenant->education_level,
            'country'         => $tenant->country,
            'province'        => $tenant->province,
            'city'            => $tenant->city,
            'address'         => $tenant->address,
            'phone'           => $tenant->phone,
            'contact_email'   => $tenant->email ?? $tenant->admin_email,
            'website'         => $tenant->website,
            'director_name'   => $tenant->director_name,
            'director_phone'  => $tenant->director_phone,
            'director_email'  => $tenant->director_email ?? $tenant->admin_email,
            'logo_url'        => $tenant->logo_url,
            'currency'        => $tenant->currency,
            'is_active'       => (bool) $tenant->is_active,
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
        $tenant = $this->resolveTenant($request, $id, write: true);

        $validated = $request->validate([
            'name'            => 'sometimes|required|string|max:255',
            'contact_email'   => 'nullable|email',
            'admin_email'     => 'nullable|email',
            'phone'           => 'nullable|string|max:30',
            'address'         => 'nullable|string|max:255',
            'nif'             => 'nullable|string|max:30',
            'website'         => 'nullable|string|max:255',
            'country'         => 'nullable|string|max:100',
            'province'        => 'nullable|string|max:100',
            'city'            => 'nullable|string|max:100',
            'director_name'   => 'nullable|string|max:255',
            'director_phone'  => 'nullable|string|max:30',
            'director_email'  => 'nullable|email',
        ]);

        if (array_key_exists('contact_email', $validated)) {
            $validated['email'] = $validated['contact_email'];
        }
        unset($validated['contact_email']);

        $tenant->update($validated);

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
        $tenant = $this->resolveTenant($request, $id, write: true);

        $request->validate([
            'file' => 'nullable|image|max:5120',
            'logo' => 'nullable|image|max:5120',
        ]);

        $file = $request->file('file') ?? $request->file('logo');

        if ($file) {
            $path = $file->store('schools/logos', 'public');
            $url = Storage::disk('public')->url($path);
            $tenant->update(['logo_url' => $url]);

            return response()->json([
                'status'  => 'success',
                'url'     => $url,
                'message' => 'Logótipo atualizado com sucesso.',
            ], 200);
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'Envie uma imagem no campo "file" ou "logo".',
        ], 422);
    }

    /**
     * Um utilizador so acede ao tenant da sua escola (o admin acede a qualquer um) e so a
     * direcao da escola (ou o admin) pode alterar o perfil.
     */
    private function resolveTenant(Request $request, ?string $id, bool $write = false): Tenant
    {
        $user = $request->user();
        $isAdmin = $user->role === 'admin';
        $tenantId = $id ?? $user->tenant_id;

        if (!$tenantId || (!$isAdmin && $tenantId !== $user->tenant_id)) {
            abort(404, 'Escola não encontrada.');
        }

        if ($write && !$isAdmin && !in_array($user->role, ['coordinator', 'coordenador', 'school', 'school_admin'], true)) {
            abort(403, 'Apenas a direção da escola pode alterar o perfil.');
        }

        return Tenant::findOrFail($tenantId);
    }
}
