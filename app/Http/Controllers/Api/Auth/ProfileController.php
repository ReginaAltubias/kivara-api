<?php

namespace App\Http\Controllers\Api\Auth;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Obter Perfil do Utilizador Autenticado
     */
        #[OA\Get(
        path: "/api/v1/auth/me",
        summary: "Auth Me (GET)",
        tags: ["Autenticação & Contas"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
                'must_change_password' => (bool) $user->must_change_password,
                'created_at' => $user->created_at,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/auth/change-password
     * Alteração obrigatória de palavra-passe no primeiro acesso ou pelo perfil.
     */
        #[OA\Post(
        path: "/api/v1/auth/change-password",
        summary: "Alterar Senha do Utilizador",
        tags: ["Autenticação & Contas"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
        ];

        if (!$user->must_change_password) {
            $rules['current_password'] = 'required|string';
        }

        $request->validate($rules);

        if (!$user->must_change_password && !Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'A palavra-passe atual está incorreta.',
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ])->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Palavra-passe alterada com sucesso.',
            'data' => [
                'user_id' => $user->id,
                'must_change_password' => false,
            ],
        ], 200);
    }
}
