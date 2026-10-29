<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    /**
     * Display a listing of the currencies.
     */
    public function index(): JsonResponse
    {
        $currencies = \App\Models\Currency::where('is_active', true)->orderBy('code')
            ->get(['code', 'name', 'symbol', 'decimal_places', 'is_active']);

        return response()->json(['status' => 'success', 'data' => $currencies]);
    }
}
