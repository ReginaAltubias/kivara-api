<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use App\Models\ChildProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Laravel\Sanctum\PersonalAccessToken;

use OpenApi\Attributes as OA;

class LoginController extends Controller
{
    /**
     * Login com Email/Password (Adultos) ou Username/PIN (Crianças)
     */
    #[OA\Post(
        path: "/api/v1/auth/login",
        summary: "Inicia sessão na plataforma KIVARA",
        tags: ["Autenticação"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/LoginRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Login bem sucedido",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "token", type: "string"),
                    new OA\Property(property: "user", ref: "#/components/schemas/User")
                ])
            ),
            new OA\Response(response: 401, description: "Credenciais inválidas"),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'string|required_without:username',
            'password' => 'required_without:pin|string',
            'username' => 'string|required_without:email',
            'pin' => 'string|max:4|required_without:password',
            'device_token' => 'string|nullable',
        ]);

        // ====================================================================
        // Modo 1: Email + Password (Adultos)
        // ====================================================================
        if ($request->filled('email') && $request->filled('password')) {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Credenciais inválidas.',
                    'error_code' => 'invalid_credentials',
                ], 401);
            }

            // Define o tempo de expiração do Access Token baseado no Role
            $expiresIn = match ($user->role) {
                'admin', 'coordinator' => 7200, // 2 horas
                'teacher' => 43200, // 12 horas
                'parent' => 86400, // 24 horas
                default => 86400, // fallback 24 horas
            };

            // Apagar tokens anteriores do mesmo device se necessário, ou manter histórico.
            // Para simplificar, não apagamos todos, apenas criamos novos.
            
            // Access Token (Curta Duração)
            $accessToken = $user->createToken('api-access', ['access-api'], now()->addSeconds($expiresIn))->plainTextToken;
            
            // Refresh Token (Longa Duração: 30 dias)
            $refreshToken = $user->createToken('api-refresh', ['issue-access-token'], now()->addDays(30))->plainTextToken;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'access_token' => $accessToken,
                    'token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => $expiresIn,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                ],
            ], 201);
        }

        // ====================================================================
        // Modo 2: Username + PIN (Crianças)
        // ====================================================================
        if ($request->filled('username') && $request->filled('pin')) {
            $child = ChildProfile::where('username', $request->username)->first();

            if (!$child || !Hash::check($request->pin, $child->pin)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Credenciais inválidas.',
                    'error_code' => 'invalid_credentials',
                ], 401);
            }

            // Cria um token para a criança (usando o ID do profile como reference)
            // Em produção, seria melhor ter um User vinculado
            $token = "child_token_" . $child->id . "_" . time();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'access_token' => $token,
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => 259200, // 3 dias
                    'user' => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'role' => 'child',
                        'kivara_phase_id' => $child->kivara_phase_id,
                    ],
                ],
            ], 201);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Dados de login inválidos.',
        ], 422);
    }
}
