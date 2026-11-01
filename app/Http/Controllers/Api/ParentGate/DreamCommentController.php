<?php

namespace App\Http\Controllers\Api\ParentGate;

use App\Http\Controllers\Controller;
use App\Models\DreamVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Comentarios do encarregado nos cofres de sonhos dos filhos. */
class DreamCommentController extends Controller
{
    public function index(Request $request, string $vaultId): JsonResponse
    {
        $this->vault($request, $vaultId);

        $rows = DB::table('dream_vault_comments')
            ->join('users', 'users.id', '=', 'dream_vault_comments.user_id')
            ->where('dream_vault_id', $vaultId)
            ->orderByDesc('dream_vault_comments.created_at')
            ->get(['dream_vault_comments.*', 'users.name as author_name', 'users.email as author_email']);

        return response()->json(['status' => 'success', 'data' => $rows->map(fn ($r) => $this->present($r))->values()]);
    }

    public function store(Request $request, string $vaultId): JsonResponse
    {
        $this->vault($request, $vaultId);
        $data = $request->validate(['text' => 'required|string|max:500', 'emoji' => 'nullable|string|max:16']);

        $id = (string) Str::uuid();
        DB::table('dream_vault_comments')->insert([
            'id' => $id,
            'dream_vault_id' => $vaultId,
            'user_id' => $request->user()->id,
            'text' => $data['text'],
            'emoji' => $data['emoji'] ?? '💬',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('dream_vault_comments')
            ->join('users', 'users.id', '=', 'dream_vault_comments.user_id')
            ->where('dream_vault_comments.id', $id)
            ->first(['dream_vault_comments.*', 'users.name as author_name', 'users.email as author_email']);

        return response()->json(['status' => 'success', 'data' => $this->present($row)], 201);
    }

    public function destroy(Request $request, string $vaultId, string $commentId): JsonResponse
    {
        $this->vault($request, $vaultId);
        $user = $request->user();

        $deleted = DB::table('dream_vault_comments')->where('id', $commentId)->where('dream_vault_id', $vaultId)
            ->when($user->role !== 'admin', fn ($q) => $q->where('user_id', $user->id))->delete();
        abort_if(! $deleted, 404);

        return response()->json(['status' => 'success', 'message' => 'Comentário eliminado.']);
    }

    private function vault(Request $request, string $id): DreamVault
    {
        $user = $request->user();

        return DreamVault::query()
            ->when($user->role !== 'admin', fn ($q) => $q->whereHas('child', fn ($c) => $c->where('parent_id', $user->id)))
            ->findOrFail($id);
    }

    private function present($r): array
    {
        return [
            'id' => $r->id,
            'text' => $r->text,
            'emoji' => $r->emoji,
            'created_at' => $r->created_at,
            'parent_profile' => ['id' => $r->user_id, 'display_name' => $r->author_name, 'email' => $r->author_email],
        ];
    }
}
