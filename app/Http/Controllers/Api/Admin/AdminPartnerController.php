<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PartnerDecisionMail;
use App\Models\Partner;
use App\Models\PartnerChallenge;
use App\Models\PartnerProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AdminPartnerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Partner::query()->withCount(['users', 'programs', 'challenges']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $like = '%' . mb_strtolower($search) . '%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(contact_email) LIKE ?', [$like])
                ->orWhereRaw('LOWER(nif) LIKE ?', [$like]));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->get()->map(fn (Partner $p) => $this->present($p)),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->present(Partner::withCount(['users', 'programs', 'challenges'])->findOrFail($id))]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $partner = Partner::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'contact_email' => 'sometimes|required|email',
            'nif' => 'nullable|string|max:30',
            'sector' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'director_name' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $partner->update($data);

        return response()->json(['status' => 'success', 'message' => 'Parceiro atualizado.', 'data' => $this->present($partner->fresh())]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $partner = Partner::findOrFail($id);
        $partner->update([
            'status' => 'approved',
            'is_active' => true,
            'rejection_reason' => null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return $this->decided($partner, true, 'Parceiro aprovado.');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'nullable|string|max:500']);
        $partner = Partner::findOrFail($id);

        $partner->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);
        $partner->users->each(fn ($u) => $u->tokens()->delete());

        return $this->decided($partner, false, 'Parceiro rejeitado.');
    }

    /** Suspende ou reativa um parceiro aprovado. */
    public function setActive(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        $partner = Partner::findOrFail($id);
        $partner->update(['is_active' => $data['is_active']]);

        if (!$data['is_active']) {
            $partner->users->each(fn ($u) => $u->tokens()->delete());
        }

        return response()->json(['status' => 'success', 'data' => $this->present($partner->fresh())]);
    }

    public function destroy(string $id): JsonResponse
    {
        $partner = Partner::findOrFail($id);

        if (PartnerProgram::where('partner_id', $id)->exists() || PartnerChallenge::where('partner_id', $id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'O parceiro tem programas ou desafios. Suspenda-o em vez de o eliminar.',
            ], 409);
        }

        $partner->users()->update(['partner_id' => null, 'is_active' => false]);
        $partner->delete();

        return response()->json(['status' => 'success', 'message' => 'Parceiro eliminado.']);
    }

    private function decided(Partner $partner, bool $approved, string $message): JsonResponse
    {
        $emailSent = true;
        try {
            Mail::to($partner->contact_email)->send(new PartnerDecisionMail($partner, $approved));
        } catch (Throwable $e) {
            report($e);
            $emailSent = false;
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'email_sent' => $emailSent,
            'data' => $this->present($partner->fresh()),
        ]);
    }

    private function present(Partner $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'contact_email' => $p->contact_email,
            'approval_status' => $p->status,
            'status' => $p->status,
            'is_active' => (bool) $p->is_active,
            'nif' => $p->nif,
            'sector' => $p->sector,
            'phone' => $p->phone,
            'country' => $p->country,
            'city' => $p->city,
            'address' => $p->address,
            'director_name' => $p->director_name,
            'rejection_reason' => $p->rejection_reason,
            'reviewed_at' => $p->reviewed_at?->toISOString(),
            'users_count' => $p->users_count ?? null,
            'programs_count' => $p->programs_count ?? null,
            'challenges_count' => $p->challenges_count ?? null,
            'created_at' => $p->created_at?->toISOString(),
        ];
    }
}
