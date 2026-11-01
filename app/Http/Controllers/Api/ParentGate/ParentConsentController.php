<?php

namespace App\Http\Controllers\Api\ParentGate;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\Consent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registos de consentimento do encarregado sobre os seus filhos
 * (GET/POST /parent/consent, GET/PUT /parent/consent/{id}, POST /parent/consent/{id}/revoke).
 */
class ParentConsentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 50), 1), 200);
        $page = max((int) $request->query('page', 1), 1);

        $query = $this->scoped($request)->with('child:id,parent_id,name,avatar')->latest();
        $total = (clone $query)->count();
        $rows = $query->forPage($page, $limit)->get()->map(fn (Consent $c) => $this->present($c));

        return response()->json([
            'status' => 'success',
            'data' => $rows,
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'child_id' => 'required|uuid',
            'consent_type' => 'required|string|max:100',
            'document_version' => 'nullable|string|max:20',
            'method' => 'nullable|string|max:30',
            'consent_data' => 'nullable|array',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $child = $this->ownedChild($request, $data['child_id']);

        $consent = Consent::updateOrCreate(
            ['child_profile_id' => $child->id, 'consent_type' => $data['consent_type']],
            [
                'is_granted' => true,
                'document_version' => $data['document_version'] ?? '1.0',
                'method' => $data['method'] ?? 'digital',
                'consent_data' => $data['consent_data'] ?? null,
                'consented_at' => now(),
                'expires_at' => $data['expires_at'] ?? null,
                'revoked_at' => null,
                'revoked_by' => null,
                'revocation_reason' => null,
            ]
        );

        return response()->json(['status' => 'success', 'data' => $this->present($consent->load('child:id,parent_id,name,avatar'))], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $consent = $this->scoped($request)->with('child:id,parent_id,name,avatar')->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $this->present($consent)]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $consent = $this->scoped($request)->with('child:id,parent_id,name,avatar')->findOrFail($id);

        $data = $request->validate([
            'document_version' => 'sometimes|string|max:20',
            'method' => 'sometimes|string|max:30',
            'consent_data' => 'sometimes|nullable|array',
            'expires_at' => 'sometimes|nullable|date',
        ]);

        $consent->update($data);

        return response()->json(['status' => 'success', 'data' => $this->present($consent->fresh('child:id,parent_id,name,avatar'))]);
    }

    public function revoke(Request $request, string $id): JsonResponse
    {
        $consent = $this->scoped($request)->with('child:id,parent_id,name,avatar')->findOrFail($id);
        $data = $request->validate(['revocation_reason' => 'nullable|string|max:500', 'reason' => 'nullable|string|max:500']);

        $consent->update([
            'is_granted' => false,
            'revoked_at' => now(),
            'revoked_by' => $request->user()->id,
            'revocation_reason' => $data['revocation_reason'] ?? $data['reason'] ?? null,
        ]);

        return response()->json(['status' => 'success', 'data' => $this->present($consent->fresh('child:id,parent_id,name,avatar'))]);
    }

    /** Consentimentos das criancas do utilizador (admin: todos). */
    private function scoped(Request $request)
    {
        $user = $request->user();

        return Consent::query()->when(
            $user->role !== 'admin',
            fn ($q) => $q->whereIn('child_profile_id', ChildProfile::where('parent_id', $user->id)->select('id'))
        );
    }

    private function ownedChild(Request $request, string $id): ChildProfile
    {
        $user = $request->user();

        return ChildProfile::query()
            ->when($user->role !== 'admin', fn ($q) => $q->where('parent_id', $user->id))
            ->findOrFail($id);
    }

    private function present(Consent $c): array
    {
        $status = match (true) {
            $c->revoked_at !== null => 'revoked',
            $c->expires_at !== null && $c->expires_at->isPast() => 'expired',
            $c->is_granted => 'granted',
            default => 'pending',
        };

        return [
            'id' => $c->id,
            'parent_id' => $c->child?->parent_id,
            'child_id' => $c->child_profile_id,
            'child' => $c->child ? ['id' => $c->child->id, 'display_name' => $c->child->name, 'avatar' => $c->child->avatar] : null,
            'consent_type' => $c->consent_type,
            'document_version' => $c->document_version,
            'status' => $status,
            'method' => $c->method,
            'consent_data' => $c->consent_data,
            'consented_at' => ($c->consented_at ?? $c->created_at)?->toISOString(),
            'expires_at' => $c->expires_at?->toISOString(),
            'revoked_at' => $c->revoked_at?->toISOString(),
            'revoked_by' => $c->revoked_by,
            'revocation_reason' => $c->revocation_reason,
            'admin_id' => null,
            'admin_action_at' => null,
            'admin_note' => null,
            'version' => 1,
            'created_at' => $c->created_at?->toISOString(),
            'updated_at' => $c->updated_at?->toISOString(),
        ];
    }
}
