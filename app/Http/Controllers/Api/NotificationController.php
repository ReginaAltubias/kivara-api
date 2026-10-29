<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    private const DEFAULT_SETTINGS = [
        'task_completed' => true,
        'mission_update' => true,
        'wallet_activity' => true,
        'badge_earned' => true,
        'weekly_summary' => true,
        'promotional' => false,
        'push_enabled' => true,
        'email_enabled' => true,
    ];

    /** GET /api/v1/notifications?unread=1&page=1&per_page=20 */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $request->boolean('unread') ? $user->unreadNotifications() : $user->notifications();

        $page = $query->paginate(min((int) $request->query('per_page', 20), 100));

        return response()->json([
            'status' => 'success',
            'data' => collect($page->items())->map(fn (DatabaseNotification $n) => $this->present($n))->values(),
            'unread_count' => $user->unreadNotifications()->count(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /** PATCH /api/v1/notifications/{id}/read */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['status' => 'success', 'data' => $this->present($notification->fresh())]);
    }

    /** POST /api/v1/notifications/mark-all-read */
    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['status' => 'success', 'data' => ['updated' => $updated]]);
    }

    /** DELETE /api/v1/notifications/{id} */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return response()->json(['status' => 'success', 'message' => 'Notificação eliminada.']);
    }

    /** GET /api/v1/notifications/settings */
    public function settings(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->settingsFor($request)]);
    }

    /** PUT /api/v1/notifications/settings */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate(
            collect(array_keys(self::DEFAULT_SETTINGS))->mapWithKeys(fn ($k) => [$k => 'sometimes|boolean'])->all()
        );

        $merged = array_merge($this->settingsFor($request), $data);
        $request->user()->forceFill(['notification_settings' => $merged])->save();

        return response()->json(['status' => 'success', 'data' => $merged]);
    }

    private function settingsFor(Request $request): array
    {
        return array_merge(self::DEFAULT_SETTINGS, $request->user()->notification_settings ?? []);
    }

    private function present(DatabaseNotification $n): array
    {
        $data = $n->data ?? [];

        return [
            'id' => $n->id,
            'type' => $data['type'] ?? class_basename($n->type),
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'data' => $data,
            'read' => $n->read_at !== null,
            'read_at' => $n->read_at?->toISOString(),
            'created_at' => $n->created_at?->toISOString(),
        ];
    }
}
