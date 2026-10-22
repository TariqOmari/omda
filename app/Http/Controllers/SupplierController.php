<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
  use App\Models\ProductPurchaseBill;
class SupplierController extends Controller
{
    // GET /api/suppliers
    public function index(Request $request)
    {
        $query = Supplier::query();

        // Optional search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('supplier_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->latest()->get();
    }

    // POST /api/suppliers
    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_name' => 'required|string|max:255',
            'address'       => 'nullable|string|max:255',
            'email'         => 'nullable|email|max:255|unique:suppliers,email',
            'phone'         => 'nullable|string|max:30',
        ]);

        $supplier = Supplier::create($data);

        return response()->json($supplier, 201);
    }

    // GET /api/suppliers/{id}
    public function show(Supplier $supplier)
    {
        return $supplier;
    }

    // PUT /api/suppliers/{id}
    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'supplier_name' => 'sometimes|required|string|max:255',
            'address'       => 'sometimes|nullable|string|max:255',
            'email'         => 'sometimes|nullable|email|max:255|unique:suppliers,email,' . $supplier->id,
            'phone'         => 'sometimes|nullable|string|max:30',
        ]);

        $supplier->update($data);

        return $supplier->fresh();
    }

    // DELETE /api/suppliers/{id}
    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted']);
    }





  

/**
 * GET /api/suppliers/{supplier}/full
 * همه چیز در یک API
 */
public function full(Supplier $supplier)
{
    // همه‌ی صورتحساب‌های این تامین‌کننده
    $bills = ProductPurchaseBill::where('supplier_id', $supplier->id)
        ->with(['product'])
        ->latest()
        ->get();

    // -------- totals --------
    $totals = [
        'bills_count'    => $bills->count(),
        'final_afn'      => 0,
        'final_usd'      => 0,
        'paid_afn'       => 0,
        'paid_usd'       => 0,
        'remaining_afn'  => 0,
        'remaining_usd'  => 0,
    ];

    $byStatus   = ['cash' => 0, 'loan_full' => 0, 'loan_partial' => 0];
    $byCurrency = ['AFN' => 0, 'USD' => 0];

    foreach ($bills as $b) {
        $totals['final_afn']     += (float) $b->final_price_afn;
        $totals['final_usd']     += (float) $b->final_price_usd;
        $totals['paid_afn']      += (float) $b->paid_amount_afn;
        $totals['paid_usd']      += (float) $b->paid_amount_usd;
        $totals['remaining_afn'] += (float) $b->remaining_amount_afn;
        $totals['remaining_usd'] += (float) $b->remaining_amount_usd;

        if (isset($byStatus[$b->payment_type]))     $byStatus[$b->payment_type]++;
        if (isset($byCurrency[$b->currency]))       $byCurrency[$b->currency]++;
    }

    // قرض‌های باز (بدهی > 0)
    $loans = $bills->filter(fn($b) => (float) $b->remaining_amount > 0)->values();

    return response()->json([
        'supplier'    => $supplier,
        'totals'      => $totals,
        'by_status'   => $byStatus,
        'by_currency' => $byCurrency,
        'loans'       => $loans,   // فقط قرض‌دارها
        'bills'       => $bills,   // همه‌ی صورتحساب‌ها
    ]);
}


}