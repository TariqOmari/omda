<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleInvoice;
use Illuminate\Http\Request;

class SaleInvoiceController extends Controller
{
    /* =========================================================
     | INDEX — GET /api/sale-invoices
     * ========================================================= */
    public function index(Request $request)
    {
        $query = SaleInvoice::with(['sale', 'customer', 'product', 'supplier']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                  ->orWhere('customer_name', 'like', "%{$s}%")
                  ->orWhere('customer_lastname', 'like', "%{$s}%")
                  ->orWhere('product_name', 'like', "%{$s}%")
                  ->orWhere('product_barcode', 'like', "%{$s}%");
            });
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->input('customer_id') === 'null'
            || $request->boolean('without_customer')) {
            $query->whereNull('customer_id');
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->input('supplier_id') === 'null'
            || $request->boolean('without_supplier')) {
            $query->whereNull('supplier_id');
        }

        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from')) {
            $query->where('sale_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('sale_date', '<=', $request->to);
        }

        if ($request->boolean('only_loans')) {
            $query->where('remaining_amount', '>', 0);
        }

        return $query->latest()->get();
    }

    /* =========================================================
     | SHOW — GET /api/sale-invoices/{id}
     * ========================================================= */
    public function show(SaleInvoice $saleInvoice)
    {
        return $saleInvoice->load(['sale', 'customer', 'product', 'supplier']);
    }

    /* =========================================================
     | SHOW BY SALE — GET /api/sales/{sale}/invoice
     * ========================================================= */
    public function showBySale(Sale $sale)
    {
        $invoice = SaleInvoice::where('sale_id', $sale->id)
            ->with(['sale', 'customer', 'product', 'supplier'])
            ->first();

        if (!$invoice) {
            return response()->json(['message' => 'فاکتور یافت نشد'], 404);
        }

        return response()->json($invoice);
    }

    /* =========================================================
     | STORE — (استفاده نمی‌شود)
     * ========================================================= */
    public function store(Request $request)
    {
        return response()->json([
            'message' => 'فاکتور به‌صورت خودکار از فروش ساخته می‌شود'
        ], 405);
    }

    /* =========================================================
     | UPDATE — (استفاده نمی‌شود)
     * ========================================================= */
    public function update(Request $request, SaleInvoice $saleInvoice)
    {
        return response()->json([
            'message' => 'ویرایش مستقیم فاکتور مجاز نیست'
        ], 405);
    }

    /* =========================================================
     | DESTROY — DELETE /api/sale-invoices/{id}
     * ========================================================= */
    public function destroy(SaleInvoice $saleInvoice)
    {
        $saleInvoice->delete();
        return response()->json(['message' => 'Sale invoice deleted']);
    }

    /* =========================================================
     | CANCEL — POST /api/sale-invoices/{id}/cancel
     * ========================================================= */
    public function cancel(SaleInvoice $saleInvoice)
    {
        if ($saleInvoice->status === 'cancelled') {
            return response()->json(['message' => 'این فاکتور قبلاً لغو شده'], 422);
        }

        $saleInvoice->update(['status' => 'cancelled']);

        return response()->json($saleInvoice->fresh());
    }

    /* =========================================================
     | STATS — GET /api/sale-invoices/stats
     * ========================================================= */
    public function stats(Request $request)
    {
        $query = SaleInvoice::query();

        if ($request->filled('from')) {
            $query->where('sale_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('sale_date', '<=', $request->to);
        }

        $invoices = $query->get();

        return response()->json([
            'total_invoices'   => $invoices->count(),
            'total_sales_afn'  => round($invoices->sum('total_price_afn'), 2),
            'total_sales_usd'  => round($invoices->sum('total_price_usd'), 2),
            'total_paid_afn'   => round($invoices->sum('paid_amount_afn'), 2),
            'total_paid_usd'   => round($invoices->sum('paid_amount_usd'), 2),
            'total_debt_afn'   => round($invoices->sum('remaining_amount_afn'), 2),
            'total_debt_usd'   => round($invoices->sum('remaining_amount_usd'), 2),
            'total_profit_afn' => round($invoices->sum('profit'), 2),
            'cancelled_count'  => $invoices->where('status', 'cancelled')->count(),
        ]);
    }
}