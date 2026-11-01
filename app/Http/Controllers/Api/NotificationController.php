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

    /**
     * POST /api/v1/notifications — professor/coordenador envia uma mensagem a uma turma,
     * a um aluno (e ao seu encarregado) ou a um encarregado das suas turmas.
     */
    public function store(Request $request): JsonResponse
    {
        $sender = $request->user();
        abort_unless(in_array($sender->role, ['teacher', 'coordinator', 'coordenador', 'admin'], true), 403);

        $data = $request->validate([
            'classroom_id' => 'nullable|uuid|exists:classrooms,id',
            'profile_id' => 'nullable|uuid',
            'recipient_id' => 'nullable|uuid',
            'title' => 'nullable|string|max:120',
            'message' => 'required|string|max:1000',
            'type' => 'nullable|string|max:60',
        ]);
        abort_if(! ($data['classroom_id'] ?? null) && ! ($data['profile_id'] ?? null) && ! ($data['recipient_id'] ?? null), 422, 'Indique a turma ou o destinatário.');

        // Turmas a que o remetente tem acesso
        $classrooms = \App\Models\Classroom::query()
            ->when($sender->role === 'teacher', fn ($q) => $q->whereHas('teachers', fn ($t) => $t->where('users.id', $sender->id)))
            ->when(in_array($sender->role, ['coordinator', 'coordenador'], true), fn ($q) => $q->where('tenant_id', $sender->tenant_id))
            ->get();

        $children = collect();
        $teens = collect();
        foreach ($classrooms as $c) {
            if (isset($data['classroom_id']) && $c->id !== $data['classroom_id']) {
                continue;
            }
            $children = $children->merge($c->childStudents()->get());
            $teens = $teens->merge($c->teenStudents()->get());
        }

        if (! empty($data['classroom_id'])) {
            abort_if($classrooms->where('id', $data['classroom_id'])->isEmpty(), 404);
        }
        if (! empty($data['profile_id'])) {
            $children = $children->where('id', $data['profile_id']);
            $teens = $teens->where('id', $data['profile_id']);
            abort_if($children->isEmpty() && $teens->isEmpty(), 404);
        }

        $recipients = collect();
        if (! empty($data['recipient_id'])) {
            $guardianIds = $children->pluck('parent_id');
            abort_unless($guardianIds->contains($data['recipient_id']), 404);
            $recipients->push(\App\Models\User::findOrFail($data['recipient_id']));
        } else {
            $recipients = $recipients->merge($children->unique('id'))->merge($teens->unique('id'))
                ->merge(\App\Models\User::whereIn('id', $children->pluck('parent_id')->filter()->unique())->get());
        }

        $notification = new \App\Notifications\TeacherMessageNotification(
            $data['title'] ?? 'Mensagem do Professor',
            $data['message'],
            $data['type'] ?? 'teacher_notification',
            $sender->name,
        );
        foreach ($recipients as $r) {
            $r->notify($notification);
        }

        return response()->json(['status' => 'success', 'message' => 'Notificação enviada.', 'recipients' => $recipients->count()], 201);
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
