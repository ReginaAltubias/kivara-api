<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantSubscription
{
    /**
     * Verifica se o Tenant do utilizador autenticado possui uma subscrição ativa.
     * Retorna HTTP 403 caso contrário.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Não autenticado.',
            ], 401);
        }

        // Administradores globais sem tenant têm bypass
        if ($user->hasRole('admin') && !$user->tenant_id) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (!$tenant || !$tenant->hasActiveSubscription()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'A subscrição da sua escola expirou. Por favor, renove para aceder a esta funcionalidade.',
            ], 403);
        }

        return $next($request);
    }
}
