<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminInvoiceController extends Controller
{
    private const TYPES = ['subscription', 'plan', 'service'];
    private const STATUSES = ['pending', 'paid', 'failed'];

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with('tenant:id,name,tenant_type');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($currency = $request->query('currency')) {
            $query->where('currency', strtoupper($currency));
        }
        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $like = '%' . mb_strtolower($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(payment_reference) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(plan_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$like])
                    ->orWhereHas('tenant', fn ($t) => $t->whereRaw('LOWER(name) LIKE ?', [$like]));
            });
        }

        $invoices = $query->latest()->get()->map(fn (Invoice $i) => $this->present($i));

        return response()->json(['status' => 'success', 'data' => $invoices]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->present(Invoice::with('tenant:id,name,tenant_type')->findOrFail($id))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => 'required|uuid|exists:tenants,id',
            'amount' => 'required|numeric|gt:0',
            'currency' => ['sometimes', 'string', 'size:3', Rule::exists((new Currency)->getTable(), 'code')],
            'type' => ['sometimes', Rule::in(self::TYPES)],
            'plan_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'payment_method' => 'sometimes|string|max:50',
        ]);

        $data['currency'] = strtoupper($data['currency'] ?? 'AOA');
        $data['payment_method'] = $data['payment_method'] ?? 'manual';
        $data['status'] = $data['status'] ?? 'pending';
        $data['type'] = $data['type'] ?? 'subscription';
        $data['payment_reference'] = $this->nextReference();
        if ($data['status'] === 'paid') {
            $data['paid_at'] = now();
        }

        $invoice = Invoice::create($data)->load('tenant:id,name,tenant_type');

        return response()->json(['status' => 'success', 'message' => 'Fatura criada.', 'data' => $this->present($invoice)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        $data = $request->validate([
            'amount' => 'sometimes|numeric|gt:0',
            'type' => ['sometimes', Rule::in(self::TYPES)],
            'plan_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'payment_method' => 'sometimes|string|max:50',
        ]);

        if ($invoice->status === 'paid' && (isset($data['amount']))) {
            return response()->json(['status' => 'error', 'message' => 'Faturas pagas nao podem alterar o valor.'], 409);
        }

        if (isset($data['status'])) {
            $data['paid_at'] = $data['status'] === 'paid' ? ($invoice->paid_at ?? now()) : null;
        }

        $invoice->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Fatura atualizada.',
            'data' => $this->present($invoice->fresh('tenant:id,name,tenant_type')),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json(['status' => 'error', 'message' => 'Faturas pagas nao podem ser eliminadas.'], 409);
        }

        $invoice->delete();

        return response()->json(['status' => 'success', 'message' => 'Fatura eliminada.']);
    }

    /** Totais por estado, por moeda (pagas) e receita mensal dos ultimos 6 meses. */
    public function summary(): JsonResponse
    {
        $byStatus = Invoice::select('status', DB::raw('COUNT(*) as total'), DB::raw('SUM(amount) as amount'))
            ->groupBy('status')->get()
            ->mapWithKeys(fn ($r) => [$r->status => ['count' => (int) $r->total, 'amount' => (float) $r->amount]]);

        $byCurrency = Invoice::where('status', 'paid')
            ->select('currency', DB::raw('SUM(amount) as amount'))
            ->groupBy('currency')->get()
            ->map(fn ($r) => ['currency' => $r->currency, 'amount' => (float) $r->amount])->values();

        $byStatusCurrency = Invoice::select('status', 'currency', DB::raw('SUM(amount) as amount'))
            ->groupBy('status', 'currency')->get()
            ->map(fn ($r) => ['status' => $r->status, 'currency' => $r->currency, 'amount' => (float) $r->amount])->values();

        $byTypeCurrency = Invoice::where('status', 'paid')
            ->select('type', 'currency', DB::raw('SUM(amount) as amount'))
            ->groupBy('type', 'currency')->get()
            ->map(fn ($r) => ['type' => $r->type, 'currency' => $r->currency, 'amount' => (float) $r->amount])->values();

        $monthly = collect(range(5, 0))->map(function (int $ago) {
            $start = now()->subMonths($ago)->startOfMonth();

            return [
                'month' => $start->format('Y-m'),
                'label' => $start->format('m/Y'),
                'amount' => (float) Invoice::where('status', 'paid')
                    ->whereBetween('paid_at', [$start, $start->copy()->endOfMonth()])->sum('amount'),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'by_status' => $byStatus,
                'by_status_currency' => $byStatusCurrency,
                'by_type_currency' => $byTypeCurrency,
                'by_currency' => $byCurrency,
                'monthly' => $monthly,
            ],
        ]);
    }

    private function nextReference(): string
    {
        $next = Invoice::whereYear('created_at', now()->year)->count() + 1;

        return sprintf('REC-%d-%06d', now()->year, $next);
    }

    private function present(Invoice $i): array
    {
        return [
            'id' => $i->id,
            'reference' => $i->payment_reference ?? 'INV-' . strtoupper(substr($i->id, 0, 8)),
            'type' => $i->type,
            'plan_name' => $i->plan_name,
            'description' => $i->description,
            'tenant_id' => $i->tenant_id,
            'tenant' => $i->tenant ? ['id' => $i->tenant->id, 'name' => $i->tenant->name, 'tenant_type' => $i->tenant->tenant_type] : null,
            'currency' => $i->currency,
            'amount' => (float) $i->amount,
            'status' => $i->status,
            'payment_method' => $i->payment_method,
            'paid_at' => $i->paid_at?->toISOString(),
            'created_at' => $i->created_at?->toISOString(),
        ];
    }
}
