<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\ChallengePrize;
use App\Models\Classroom;
use App\Models\PartnerChallenge;
use App\Support\PartnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Premios que o parceiro atribui e entrega as turmas/escolas dos seus desafios. */
class PartnerPrizeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $partner = PartnerContext::resolve($request);
        $rows = ChallengePrize::where('partner_id', $partner->id)->latest()->get();

        return response()->json(['status' => 'success', 'data' => $rows->map->present()->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $partner = PartnerContext::resolve($request);
        $data = $request->validate([
            'partner_challenge_id' => 'required|uuid',
            'classroom_id' => 'required|uuid',
            'title' => 'required|string|max:255',
            'category' => 'required|in:material,financial,license',
            'value_aoa' => 'required|numeric|min:0',
            'delivery_address' => 'nullable|string|max:255',
            'recipient_representative' => 'nullable|string|max:255',
        ]);

        $challenge = PartnerChallenge::where('partner_id', $partner->id)->findOrFail($data['partner_challenge_id']);
        // so turmas inscritas no desafio
        abort_unless(
            DB::table('classroom_challenge')->where('partner_challenge_id', $challenge->id)->where('classroom_id', $data['classroom_id'])->exists(),
            422,
            'A turma não está inscrita neste desafio.'
        );
        $classroom = Classroom::findOrFail($data['classroom_id']);

        $prize = ChallengePrize::create($data + [
            'partner_id' => $partner->id,
            'tenant_id' => $classroom->tenant_id,
            'status' => 'processing',
            'tracking_code' => 'PRM-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
        ]);

        return response()->json(['status' => 'success', 'data' => $prize->present()], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $partner = PartnerContext::resolve($request);
        $prize = ChallengePrize::where('partner_id', $partner->id)->findOrFail($id);

        $data = $request->validate([
            'status' => 'sometimes|in:active,processing,delivered',
            'delivery_date' => 'sometimes|nullable|date',
            'recipient_representative' => 'sometimes|nullable|string|max:255',
            'delivery_address' => 'sometimes|nullable|string|max:255',
            'proof_document_url' => 'sometimes|nullable|url|max:500',
        ]);

        if (($data['status'] ?? null) === 'delivered' && empty($data['delivery_date']) && ! $prize->delivery_date) {
            $data['delivery_date'] = now()->toDateString();
        }
        $prize->update($data);

        return response()->json(['status' => 'success', 'data' => $prize->fresh()->present()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $partner = PartnerContext::resolve($request);
        $prize = ChallengePrize::where('partner_id', $partner->id)->findOrFail($id);
        abort_if($prize->status === 'delivered', 422, 'Um prémio entregue não pode ser eliminado.');
        $prize->delete();

        return response()->json(['status' => 'success', 'message' => 'Prémio eliminado.']);
    }
}
