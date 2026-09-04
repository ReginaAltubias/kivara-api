<?php

namespace App\Http\Controllers\Api\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderLoginBannersRequest;
use App\Http\Requests\Admin\StoreLoginBannerRequest;
use App\Models\LoginBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminBannerController extends Controller
{
    /**
     * Lista banners ordenados para o painel Admin.
     */
        #[OA\Get(
        path: "/api/v1/admin/login-banners",
        summary: "Listar Banners de Login",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(): JsonResponse
    {
        $banners = LoginBanner::query()->orderBy('order')->get();

        return response()->json([
            'status' => 'success',
            'data' => $banners,
        ]);
    }

    /**
     * Cria banner com upload de imagem.
     */
        #[OA\Post(
        path: "/api/v1/admin/login-banners",
        summary: "Criar ou Atualizar Banner de Login",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function store(StoreLoginBannerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $path = $request->file('image')->store('login-banners', 'public');

        $banner = LoginBanner::create([
            'title' => $validated['title'],
            'image_path' => $path,
            'link_url' => $validated['link_url'] ?? null,
            'order' => $validated['order'] ?? ((int) LoginBanner::max('order') + 1),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Banner criado com sucesso.',
            'data' => $banner,
        ], 201);
    }

    /**
     * Detalhe de um banner.
     */
        #[OA\Get(
        path: "/api/v1/admin/login-banners/{login_banner}",
        summary: "Listar Banners de Login",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(LoginBanner $login_banner): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $login_banner,
        ]);
    }

    /**
     * Atualiza metadados do banner (imagem opcional).
     */
        #[OA\Patch(
        path: "/api/v1/admin/login-banners/{login_banner}",
        summary: "Admin Login Banners {login Banner} (PATCH)",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function update(Request $request, LoginBanner $login_banner): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            if ($login_banner->image_path) {
                Storage::disk('public')->delete($login_banner->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('login-banners', 'public');
        }

        unset($validated['image']);
        $login_banner->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Banner atualizado com sucesso.',
            'data' => $login_banner->fresh(),
        ]);
    }

    /**
     * Reordena banners (drag-and-drop).
     */
        #[OA\Post(
        path: "/api/v1/admin/login-banners/reorder",
        summary: "Criar ou Atualizar Banner de Login",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function reorder(ReorderLoginBannersRequest $request): JsonResponse
    {
        $banners = $request->validated('banners');

        DB::transaction(function () use ($banners) {
            foreach ($banners as $item) {
                LoginBanner::where('id', $item['id'])->update([
                    'order' => $item['order'],
                ]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Ordem dos banners atualizada com sucesso.',
            'data' => LoginBanner::query()->orderBy('order')->get(),
        ]);
    }

    /**
     * Remove banner e ficheiro no Storage.
     */
        #[OA\Delete(
        path: "/api/v1/admin/login-banners/{login_banner}",
        summary: "Admin Login Banners {login Banner} (DELETE)",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 204, description: "Recurso removido com sucesso.")
        ]
    )]
    public function destroy(LoginBanner $login_banner): JsonResponse
    {
        if ($login_banner->image_path) {
            Storage::disk('public')->delete($login_banner->image_path);
        }

        $login_banner->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Banner removido com sucesso.',
        ]);
    }
}
