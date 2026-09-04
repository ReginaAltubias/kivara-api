<?php

namespace App\Http\Controllers\Api\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminAuditController extends Controller
{
    /**
     * Lista paginada de eventos de auditoria.
     */
        #[OA\Get(
        path: "/api/v1/admin/audit-logs",
        summary: "Listar Logs de Auditoria do Sistema",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->string('user_id')))
            ->when($request->filled('model_type'), fn ($q) => $q->where('model_type', $request->string('model_type')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
            ->latest()
            ->paginate(50);

        return response()->json([
            'status' => 'success',
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * Bloqueia ou desbloqueia uma conta suspeita (security-check).
     */
        #[OA\Post(
        path: "/api/v1/auth/security-check",
        summary: "Auth Security Check (POST)",
        tags: ["Autenticação & Contas"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function securityCheck(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'action' => 'required|in:lock-account,unlock-account',
                'email' => 'required|email|exists:users,email',
            ]);

            $target = User::where('email', $validated['email'])->firstOrFail();
            $isActive = $validated['action'] === 'unlock-account';
            $previous = $target->is_active;

            $target->is_active = $isActive;
            $target->save();

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'event' => 'security_action',
                'model_type' => User::class,
                'model_id' => $target->id,
                'old_values' => [
                    'is_active' => $previous,
                    'action' => $validated['action'],
                ],
                'new_values' => [
                    'is_active' => $isActive,
                    'email' => $target->email,
                    'action' => $validated['action'],
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $isActive
                    ? 'Conta desbloqueada com sucesso.'
                    : 'Conta bloqueada com sucesso.',
                'data' => [
                    'id' => $target->id,
                    'email' => $target->email,
                    'is_active' => $target->is_active,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dados de validação inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não foi possível executar a ação de segurança.',
            ], 500);
        }
    }
}
