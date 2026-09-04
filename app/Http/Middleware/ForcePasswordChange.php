<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->must_change_password) {
            // Se o request já for para a rota de mudar a password, deixa passar.
            // Assumimos que a rota será algo como 'api/v1/auth/change-password'
            if (!$request->is('api/v1/auth/change-password')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'É obrigatório alterar a palavra-passe antes de continuar.',
                    'code' => 'FORCE_PASSWORD_CHANGE'
                ], 403);
            }
        }

        return $next($request);
    }
}
