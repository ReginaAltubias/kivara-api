<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MediaUploadController extends Controller
{
    public function __construct(
        protected MediaStorageService $mediaStorageService
    ) {}

    /**
     * POST /api/v1/media - Upload de arquivos de mídia (imagens, vídeos, áudios, PDFs, JSON)
     */
        #[OA\Post(
        path: "/api/v1/media",
        summary: "Media (POST)",
        tags: ["Geral"],
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
            'file' => 'required|file|max:51200', // max 50MB
        ]);

        try {
            $result = $this->mediaStorageService->storeMedia(
                file: $request->file('file'),
                folder: $request->input('folder', 'media')
            );

            return response()->json($result, 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/profiles/avatar - Upload de Avatar de Usuário / Criança
     */
        #[OA\Post(
        path: "/api/v1/profiles/avatar",
        summary: "Profiles Avatar (POST)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function avatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|file|image|max:2048', // max 2MB
        ]);

        try {
            $result = $this->mediaStorageService->storeAvatar(
                file: $request->file('avatar'),
                userOrProfile: $request->user()
            );

            return response()->json($result, 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
