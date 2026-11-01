<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerProgram;
use App\Models\User;
use App\Support\PartnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Convites de programa: o parceiro gera um codigo (familias ou escolas) e o destinatario aceita/recusa.
 */
class ProgramInvitationController extends Controller
{
    // ------------------------------------------------------------------ parceiro

    /** GET /partner/programs/{id}/invitations e GET /partner/invitations */
    public function index(Request $request, ?string $id = null): JsonResponse
    {
        $partner = PartnerContext::resolve($request);
        $rows = DB::table('program_invitations')
            ->where('partner_id', $partner->id)
            ->when($id, fn ($q) => $q->where('program_id', $id))
            ->orderByDesc('created_at')->get();

        return response()->json(['status' => 'success', 'data' => $this->present($rows, $partner->name)]);
    }

    /** POST /partner/programs/{id}/invite */
    public function store(Request $request, string $id): JsonResponse
    {
        $partner = PartnerContext::resolve($request);
        $program = PartnerProgram::where('partner_id', $partner->id)->findOrFail($id);

        $data = $request->validate([
            'invite_type' => 'nullable|in:household,school',
            'target_type' => 'nullable|in:household,school',
            'expires_days' => 'nullable|integer|min:1|max:365',
        ]);
        $type = $data['invite_type'] ?? $data['target_type'] ?? 'household';

        $already = DB::table('program_invitations')->where('program_id', $program->id)->where('invite_type', $type)->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();
        abort_if($already, 409, 'Já existe um convite pendente deste tipo para o programa.');

        do {
            $code = strtoupper(Str::random(8));
        } while (DB::table('program_invitations')->where('code', $code)->exists());

        $rowId = (string) Str::uuid();
        DB::table('program_invitations')->insert([
            'id' => $rowId, 'partner_id' => $partner->id, 'program_id' => $program->id, 'invite_type' => $type, 'code' => $code,
            'status' => 'pending', 'expires_at' => now()->addDays((int) ($data['expires_days'] ?? 30)),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'data' => $this->present(DB::table('program_invitations')->where('id', $rowId)->get(), $partner->name)[0]], 201);
    }

    /** DELETE /partner/invitations/{id} — revoga um convite pendente. */
    public function revoke(Request $request, string $id): JsonResponse
    {
        $partner = PartnerContext::resolve($request);
        $row = DB::table('program_invitations')->where('partner_id', $partner->id)->where('id', $id)->first();
        abort_if(! $row, 404);
        abort_if($row->status !== 'pending', 422, 'Só convites pendentes podem ser revogados.');
        DB::table('program_invitations')->where('id', $id)->update(['status' => 'revoked', 'updated_at' => now()]);

        return response()->json(['status' => 'success', 'message' => 'Convite revogado.']);
    }

    // ------------------------------------------------------------------ destinatario (familia / escola)

    /** GET /invitations/mine — convites aplicaveis ao utilizador (e os que ja aceitou). */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $this->typeFor($user);

        $rows = DB::table('program_invitations')
            ->where(function ($q) use ($user, $type) {
                $q->where('accepted_by_user_id', $user->id);
                if ($type) {
                    $q->orWhere(fn ($w) => $w->where('invite_type', $type)->where('status', 'pending')
                        ->where(fn ($e) => $e->whereNull('expires_at')->orWhere('expires_at', '>', now())));
                }
            })->orderByDesc('created_at')->get();

        return response()->json(['status' => 'success', 'data' => $this->present($rows)]);
    }

    /** GET /partner/invitations/{code}/validate */
    public function validateCode(Request $request, string $code): JsonResponse
    {
        $row = DB::table('program_invitations')->where('code', strtoupper($code))->first();
        if (! $row) {
            return response()->json(['valid' => false, 'error' => 'not_found', 'message' => 'Convite não encontrado.']);
        }

        $status = $this->effectiveStatus($row);
        if ($status !== 'pending') {
            return response()->json(['valid' => false, 'status' => $status, 'error' => $status, 'message' => 'Este convite já não está disponível.']);
        }

        $program = PartnerProgram::with('partner')->find($row->program_id);

        return response()->json([
            'valid' => true,
            'status' => 'pending',
            'invite_type' => $row->invite_type,
            'target_type' => $row->invite_type,
            'partner_name' => $program?->partner?->name,
            'program' => ['id' => $program?->id, 'program_name' => $program?->title, 'description' => $program?->description],
        ]);
    }

    public function accept(Request $request): JsonResponse
    {
        return $this->resolve($request, 'accepted');
    }

    public function reject(Request $request): JsonResponse
    {
        return $this->resolve($request, 'rejected');
    }

    // ------------------------------------------------------------------ helpers

    private function resolve(Request $request, string $to): JsonResponse
    {
        $data = $request->validate(['code' => 'required_without:invite_code|string|max:20', 'invite_code' => 'nullable|string|max:20']);
        $code = strtoupper($data['code'] ?? $data['invite_code']);
        $user = $request->user();

        return DB::transaction(function () use ($code, $user, $to) {
            $row = DB::table('program_invitations')->where('code', $code)->lockForUpdate()->first();
            abort_if(! $row, 404, 'Convite não encontrado.');
            abort_if($this->effectiveStatus($row) !== 'pending', 422, 'Este convite já não está disponível.');
            abort_if($this->typeFor($user) !== $row->invite_type, 403, 'Este convite não é para o seu tipo de conta.');

            $update = ['status' => $to, 'accepted_by_user_id' => $user->id, 'updated_at' => now()];
            if ($to === 'accepted') {
                $update['accepted_at'] = now();
                $row->invite_type === 'school'
                    ? $update['accepted_school_tenant_id'] = $user->tenant_id
                    : $update['accepted_household_id'] = $user->family_id;
            }
            DB::table('program_invitations')->where('id', $row->id)->update($update);

            return response()->json(['status' => 'success', 'success' => true, 'data' => ['code' => $code, 'status' => $to]]);
        });
    }

    private function typeFor(User $user): ?string
    {
        return match ($user->role) {
            'parent' => 'household',
            'coordinator', 'coordenador', 'school_admin' => 'school',
            default => null,
        };
    }

    private function effectiveStatus(object $row): string
    {
        if ($row->status === 'pending' && $row->expires_at && now()->gt($row->expires_at)) {
            return 'expired';
        }

        return $row->status;
    }

    private function present($rows, ?string $partnerName = null): array
    {
        $programs = PartnerProgram::with('partner:id,name')->whereIn('id', collect($rows)->pluck('program_id')->unique())->get()->keyBy('id');
        $users = User::whereIn('id', collect($rows)->pluck('accepted_by_user_id')->filter()->unique())->get(['id', 'name'])->keyBy('id');

        return collect($rows)->map(fn ($r) => [
            'id' => $r->id,
            'program_id' => $r->program_id,
            'program_name' => $programs[$r->program_id]->title ?? null,
            'program_description' => $programs[$r->program_id]->description ?? null,
            'partner_name' => $partnerName ?? ($programs[$r->program_id]->partner->name ?? null),
            'invite_type' => $r->invite_type,
            'target_type' => $r->invite_type,
            'invite_code' => $r->code,
            'code' => $r->code,
            'status' => $this->effectiveStatus($r),
            'expires_at' => $r->expires_at,
            'created_at' => $r->created_at,
            'accepted_at' => $r->accepted_at,
            'accepted_by_name' => $r->accepted_by_user_id ? ($users[$r->accepted_by_user_id]->name ?? null) : null,
            'accepted_profile_id' => $r->accepted_by_user_id,
            'accepted_household_id' => $r->accepted_household_id,
            'accepted_school_tenant_id' => $r->accepted_school_tenant_id,
        ])->values()->all();
    }
}
