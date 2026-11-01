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
            'data' => $this->present($user),
        ], 200);
    }

    /**
     * PATCH /api/v1/auth/me — actualiza os dados pessoais do proprio utilizador.
     * Papel, e-mail e inquilino nao se alteram por aqui.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'sometimes|string|min:2|max:255',
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]{6,30}$/'],
            'avatar' => 'sometimes|nullable|string|max:1500000',
            'currency' => 'sometimes|nullable|string|size:3|alpha',
            'country' => 'sometimes|nullable|string|max:100',
            'gender' => 'sometimes|nullable|string|in:male,female,other,masculino,feminino,outro',
            'language' => 'sometimes|nullable|string|in:pt,en,fr,es',
        ]);

        $currency = $data['currency'] ?? null;
        unset($data['currency']);
        $user->fill($data)->save();

        // A moeda da familia segue o pais escolhido pelo encarregado (so o seu proprio inquilino familiar).
        if ($currency && $user->tenant_id && $user->role !== 'admin') {
            \App\Models\Tenant::where('id', $user->tenant_id)->where('tenant_type', 'family')
                ->update(['currency' => strtoupper($currency)]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->present($user->fresh()),
        ], 200);
    }

    private function present($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'tenant_id' => $user->tenant_id,
            'household_id' => $user->family_id,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'country' => $user->country,
            'gender' => $user->gender,
            'language' => $user->language,
            'must_change_password' => (bool) $user->must_change_password,
            'created_at' => $user->created_at,
        ];
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
