<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Tenant;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCurrencyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => Currency::orderBy('code')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', 'unique:currencies,code'],
            'name' => 'required|string|max:100',
            'symbol' => 'required|string|max:10',
            'decimal_places' => 'sometimes|integer|min:0|max:6',
            'is_active' => 'sometimes|boolean',
        ]);

        $currency = Currency::create($data)->fresh();

        return response()->json(['status' => 'success', 'message' => 'Moeda criada.', 'data' => $currency], 201);
    }

    public function show(string $code): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => Currency::findOrFail(strtoupper($code))]);
    }

    public function update(Request $request, string $code): JsonResponse
    {
        $currency = Currency::findOrFail(strtoupper($code));

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'symbol' => 'sometimes|required|string|max:10',
            'decimal_places' => 'sometimes|integer|min:0|max:6',
            'is_active' => 'sometimes|boolean',
        ]);

        $currency->update($data);

        return response()->json(['status' => 'success', 'message' => 'Moeda atualizada.', 'data' => $currency->fresh()]);
    }

    public function toggleActive(string $code): JsonResponse
    {
        $currency = Currency::findOrFail(strtoupper($code));

        if ($currency->is_active && $this->inUse($currency->code)) {
            return response()->json([
                'status' => 'error',
                'message' => 'A moeda esta em uso (tenants ou carteiras) e nao pode ser desativada.',
            ], 409);
        }

        $currency->update(['is_active' => !$currency->is_active]);

        return response()->json(['status' => 'success', 'data' => $currency->fresh()]);
    }

    public function destroy(string $code): JsonResponse
    {
        $currency = Currency::findOrFail(strtoupper($code));

        if ($this->inUse($currency->code) || ExchangeRate::where('from_currency', $currency->code)->orWhere('to_currency', $currency->code)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'A moeda esta em uso (tenants, carteiras ou taxas de cambio). Desative-a em vez de a eliminar.',
            ], 409);
        }

        $currency->delete();

        return response()->json(['status' => 'success', 'message' => 'Moeda eliminada.']);
    }

    private function inUse(string $code): bool
    {
        return Tenant::where('currency', $code)->exists() || Wallet::where('currency', $code)->exists();
    }
}
