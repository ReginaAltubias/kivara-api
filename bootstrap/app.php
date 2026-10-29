<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // $middleware->statefulApi(); // Desativado para usar Bearer Tokens no localhost sem ser bloqueado pela falta de cookies de sessão

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'webhook.signature' => \App\Http\Middleware\VerifyWebhookSignature::class,
            'user.role' => \App\Http\Middleware\EnsureUserRole::class,
            'force_password_change' => \App\Http\Middleware\ForcePasswordChange::class,
            'subscribed.tenant' => \App\Http\Middleware\CheckTenantSubscription::class,
            'tenant.subscribed' => \App\Http\Middleware\CheckTenantSubscription::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Envelope de erro unico para a API: {status:"error", message, errors?}.
        // Nunca expoe trace, ficheiros nem SQL; os detalhes ficam apenas nos logs.
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            $error = fn (string $message, int $status, array $extra = []) => response()->json(
                ['status' => 'error', 'message' => $message] + $extra,
                $status
            );

            return match (true) {
                $e instanceof \Illuminate\Validation\ValidationException
                    => $error('Dados de validação inválidos.', 422, ['errors' => $e->errors()]),
                $e instanceof \Illuminate\Auth\AuthenticationException
                    => $error('Não autenticado.', 401),
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException,
                $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
                    => $error('Recurso não encontrado.', 404),
                $e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
                    => $error('Método não permitido para este recurso.', 405),
                $e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException
                    => $error('Demasiados pedidos. Tente novamente dentro de instantes.', 429),
                $e instanceof \Illuminate\Auth\Access\AuthorizationException
                    => $error('Acesso não autorizado para o perfil do utilizador.', 403),
                $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    => $error($e->getMessage() !== '' ? $e->getMessage() : 'Pedido inválido.', $e->getStatusCode()),
                default
                    => $error('Erro interno do servidor.', 500),
            };
        });
    })->create();
