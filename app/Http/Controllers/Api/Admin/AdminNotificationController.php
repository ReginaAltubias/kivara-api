<?php

namespace App\Http\Controllers\Api\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendSystemNotificationRequest;
use App\Models\User;
use App\Notifications\SystemAnnouncementNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class AdminNotificationController extends Controller
{
    /**
     * Envia comunicado em massa (database + Expo Push).
     */
        #[OA\Post(
        path: "/api/v1/admin/notifications/send",
        summary: "Admin Notifications Send (POST)",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function send(SendSystemNotificationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $audience = $validated['target_audience'];

        $query = User::query()->where('is_active', true);

        match ($audience) {
            'parents' => $query->where('role', 'parent'),
            'teachers' => $query->where('role', 'teacher'),
            'teens' => $query->whereIn('role', ['teen', 'yang']),
            default => null,
        };

        $notification = new SystemAnnouncementNotification(
            $validated['title'],
            $validated['message'],
            $audience,
        );

        $sent = 0;

        $query->select(['id', 'email', 'name', 'role'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($notification, &$sent) {
                Notification::send($users, clone $notification);
                $sent += $users->count();
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Notificação enfileirada/enviada com sucesso.',
            'data' => [
                'target_audience' => $audience,
                'recipients' => $sent,
            ],
        ], 202);
    }
}
