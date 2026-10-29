<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminExchangeRateController extends Controller
{
    public function index(): JsonResponse
    {
        $rates = ExchangeRate::orderBy('from_currency')->orderBy('to_currency')->get()
            ->map(fn (ExchangeRate $r) => $this->present($r));

        return response()->json(['status' => 'success', 'data' => $rates]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'base_currency' => strtoupper((string) $request->input('base_currency')),
            'target_currency' => strtoupper((string) $request->input('target_currency')),
        ]);

        $data = $request->validate([
            'base_currency' => 'required|string|size:3|exists:currencies,code',
            'target_currency' => 'required|string|size:3|different:base_currency|exists:currencies,code',
            'rate' => 'required|numeric|gt:0',
        ]);

        if (ExchangeRate::where('from_currency', $data['base_currency'])->where('to_currency', $data['target_currency'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ja existe uma taxa para este par de moedas. Edite-a.',
            ], 422);
        }

        $rate = ExchangeRate::create([
            'from_currency' => $data['base_currency'],
            'to_currency' => $data['target_currency'],
            'rate' => $data['rate'],
        ])->fresh();

        return response()->json(['status' => 'success', 'message' => 'Taxa criada.', 'data' => $this->present($rate)], 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->present(ExchangeRate::findOrFail($id))]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $rate = ExchangeRate::findOrFail($id);
        $data = $request->validate(['rate' => 'required|numeric|gt:0']);

        $rate->update($data);

        return response()->json(['status' => 'success', 'message' => 'Taxa atualizada.', 'data' => $this->present($rate->fresh())]);
    }

    public function destroy(string $id): JsonResponse
    {
        ExchangeRate::findOrFail($id)->delete();

        return response()->json(['status' => 'success', 'message' => 'Taxa eliminada.']);
    }

    private function present(ExchangeRate $r): array
    {
        return [
            'id' => $r->id,
            'base_currency' => $r->from_currency,
            'target_currency' => $r->to_currency,
            'rate' => (float) $r->rate,
            'updated_at' => $r->updated_at?->toISOString(),
        ];
    }
}
