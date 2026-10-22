<?php

namespace App\Http\Controllers;

use App\Models\ProductPurchaseBill;
use Illuminate\Http\Request;

class ProductPurchaseBillController extends Controller
{
    /* =========================================================
     | INDEX — GET /api/product-purchase-bills
     * ========================================================= */
    public function index(Request $request)
    {
        $q = ProductPurchaseBill::with(['product', 'supplier']);

        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function ($x) use ($s) {
                $x->where('bill_number', 'like', "%{$s}%")
                  ->orWhere('product_name', 'like', "%{$s}%")
                  ->orWhere('supplier_name', 'like', "%{$s}%");
            });
        }

        if ($request->filled('supplier_id')) {
            $q->where('supplier_id', $request->supplier_id);
        }

        // ✅ NEW: filter bills whose product has NO supplier
        if ($request->input('supplier_id') === 'null'
            || $request->boolean('without_supplier')) {
            $q->whereNull('supplier_id');
        }

        if ($request->filled('payment_type')) {
            $q->where('payment_type', $request->payment_type);
        }

        if ($request->filled('currency')) {
            $q->where('currency', $request->currency);
        }

        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->to);
        }

        return $q->latest()->get();
    }

    /* =========================================================
     | SHOW — GET /api/product-purchase-bills/{bill}
     * ========================================================= */
    public function show(ProductPurchaseBill $bill)
    {
        return $bill->load(['product', 'supplier']);
    }

    /* =========================================================
     | DESTROY — DELETE /api/product-purchase-bills/{bill}
     * ========================================================= */
    public function destroy(ProductPurchaseBill $bill)
    {
        $bill->delete();

        return response()->json(['message' => 'Bill deleted']);
    }
}