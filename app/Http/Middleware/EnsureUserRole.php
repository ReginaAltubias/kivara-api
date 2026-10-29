<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que o utilizador autenticado tenha um dos papeis indicados, pela coluna
 * `users.role` ou por papeis Spatie. Uso: `user.role:admin`, `user.role:teen,yang`
 * ou `user.role:coordinator|teacher`.
 *
 * Os tokens emitidos no login so tem a habilidade `access-api`, por isso nao se
 * pode usar `ability:role:*` para autorizar por papel. Os sinonimos da direcao da
 * escola (`coordenador`, `school`, `school_admin`) contam como `coordinator`.
 */
class EnsureUserRole
{
    private const ALIASES = [
        'coordenador' => 'coordinator',
        'school' => 'coordinator',
        'school_admin' => 'coordinator',
        'super_admin' => 'admin',
    ];

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Nao autenticado.');
        }

        $required = collect($roles)
            ->flatMap(fn (string $r) => explode('|', $r))
            ->map(fn (string $r) => $this->normalize($r))
            ->filter()->unique()->values();

        $owned = collect([$user->role])
            ->merge(method_exists($user, 'getRoleNames') ? $user->getRoleNames() : [])
            ->filter()
            ->map(fn (string $r) => $this->normalize($r))
            ->unique();

        if ($required->intersect($owned)->isEmpty()) {
            abort(403, 'Acesso nao autorizado para o perfil do utilizador.');
        }

        return $next($request);
    }

    private function normalize(string $role): string
    {
        $role = trim($role);

        return self::ALIASES[$role] ?? $role;
    }
}
