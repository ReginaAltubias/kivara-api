<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegionalPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRegionalPriceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = RegionalPrice::query()
            ->when($request->query('tier_id', $request->query('plan_id')), fn ($q, $id) => $q->where('plan_id', $id))
            ->orderBy('currency_code')->get()->map(fn (RegionalPrice $r) => $this->present($r));

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'plan_id' => $request->input('plan_id', $request->input('tier_id')),
            'currency_code' => strtoupper((string) $request->input('currency_code')),
        ]);

        $data = $request->validate([
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
            'currency_code' => 'required|string|size:3|exists:currencies,code',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'extra_child_price' => 'sometimes|numeric|min:0',
        ]);

        if (RegionalPrice::where('plan_id', $data['plan_id'])->where('currency_code', $data['currency_code'])->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Ja existe um preco regional para este plano e moeda.'], 422);
        }

        $row = RegionalPrice::create($data)->fresh();

        return response()->json(['status' => 'success', 'data' => $this->present($row)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $row = RegionalPrice::findOrFail($id);
        $data = $request->validate([
            'price_monthly' => 'sometimes|numeric|min:0',
            'price_yearly' => 'sometimes|numeric|min:0',
            'extra_child_price' => 'sometimes|numeric|min:0',
        ]);

        $row->update($data);

        return response()->json(['status' => 'success', 'data' => $this->present($row->fresh())]);
    }

    public function destroy(string $id): JsonResponse
    {
        RegionalPrice::findOrFail($id)->delete();

        return response()->json(['status' => 'success', 'message' => 'Preco regional eliminado.']);
    }

    private function present(RegionalPrice $r): array
    {
        return [
            'id' => $r->id,
            'tier_id' => $r->plan_id,
            'plan_id' => $r->plan_id,
            'currency_code' => $r->currency_code,
            'price_monthly' => (float) $r->price_monthly,
            'price_yearly' => (float) $r->price_yearly,
            'extra_child_price' => (float) $r->extra_child_price,
        ];
    }
}
