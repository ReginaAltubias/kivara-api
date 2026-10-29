<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que o utilizador autenticado tenha um dos papeis indicados, pela coluna
 * `users.role` ou por papeis Spatie. Uso: `user.role:admin` ou `user.role:teen,yang`.
 * Os tokens emitidos no login so tem a habilidade `access-api`, por isso nao se
 * pode usar `ability:role:*` para autorizar por papel.
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Nao autenticado.');
        }

        $allowed = in_array($user->role, $roles, true)
            || (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles))
            || (in_array('admin', $roles, true) && method_exists($user, 'hasRole') && $user->hasRole('super_admin'));

        if (!$allowed) {
            abort(403, 'Acesso nao autorizado para o perfil do utilizador.');
        }

        return $next($request);
    }
}
