<?php

namespace App\Http\Controllers\Api\Auth;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Mail\AccountActivationMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ActivationController extends Controller
{
    /**
     * POST /api/v1/auth/verify-activation
     * 
     * Valida o código OTP de 6 dígitos, ativa a conta, verifica o e-mail
     * e gera o token de autenticação Sanctum com a flag must_change_password.
     */
        #[OA\Post(
        path: "/api/v1/auth/verify-activation",
        summary: "Ativar Conta com Código OTP (6 dígitos)",
        tags: ["Autenticação & Contas"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code'  => 'required|string|min:4|max:10',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Utilizador não encontrado.',
            ], 404);
        }

        // Validação do código de ativação
        if (empty($user->activation_code) || trim($user->activation_code) !== trim($request->code)) {
            return response()->json([
                'status'     => 'error',
                'message'    => 'Código de ativação inválido.',
                'error_code' => 'invalid_activation_code',
            ], 422);
        }

        // Validação de expiração (24 horas)
        if ($user->activation_expires_at && $user->activation_expires_at->isPast()) {
            return response()->json([
                'status'     => 'error',
                'message'    => 'O código de ativação expirou. Por favor, solicite um novo código.',
                'error_code' => 'activation_code_expired',
            ], 422);
        }

        // Ativação da conta
        $user->forceFill([
            'activation_code'       => null,
            'activation_expires_at' => null,
            'email_verified_at'     => now(),
            'is_active'             => true,
        ])->save();

        // Geração do token Sanctum (login automático após ativação)
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Conta ativada com sucesso!',
            'data'    => [
                'token'                => $token,
                'access_token'         => $token,
                'token_type'           => 'Bearer',
                'must_change_password' => (bool) $user->must_change_password,
                'user'                 => [
                    'id'                   => $user->id,
                    'name'                 => $user->name,
                    'email'                => $user->email,
                    'role'                 => $user->role,
                    'must_change_password' => (bool) $user->must_change_password,
                    'email_verified_at'    => $user->email_verified_at?->toISOString(),
                    'tenant_id'            => $user->tenant_id,
                ],
            ],
        ], 200);
    }

    /**
     * POST /api/v1/auth/resend-activation
     * 
     * Reenvia um novo código OTP para o e-mail do utilizador se ainda não estiver verificado.
     */
        #[OA\Post(
        path: "/api/v1/auth/resend-activation",
        summary: "Reenviar Código de Ativação",
        tags: ["Autenticação & Contas"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function resend(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        $newPassword = Str::password(8, true, true, false, false);
        $newCode = (string) random_int(100000, 999999);

        $user->forceFill([
            'password'              => bcrypt($newPassword),
            'activation_code'       => $newCode,
            'activation_expires_at' => now()->addHours(24),
            'must_change_password'  => true,
        ])->save();

        Mail::to($user->email)->queue(new AccountActivationMail($user, $newPassword, $newCode));

        return response()->json([
            'status'  => 'success',
            'message' => 'Novo código de ativação enviado para o seu e-mail.',
        ], 200);
    }
}
