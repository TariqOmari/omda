<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    /* =========================================================
     | CUSTOMER LOANS — GET /api/loans/customers
     | همه قرض‌های مشتریان (از جدول sales)
     * ========================================================= */
    public function customerLoans(Request $request)
    {
        $query = Sale::with(['customer', 'product'])
            ->where('remaining_amount', '>', 0);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('from')) {
            $query->where('sale_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('sale_date', '<=', $request->to);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                  ->orWhere('customer_name', 'like', "%{$s}%")
                  ->orWhere('product_name', 'like', "%{$s}%");
            });
        }

        $sales = $query->latest()->get();

        // گروه‌بندی بر اساس مشتری
        $grouped = $sales->groupBy('customer_id')->map(function ($items) {
            $first = $items->first();
            return [
                'customer_id'    => $first->customer_id,
                'customer_name'  => $first->customer_name,
                'customer_phone' => $first->customer?->phone,
                'customer_photo' => $first->customer?->profile_photo_url,
                'invoices_count' => $items->count(),
                'total_loans'    => round($items->sum('total_price'), 2),
                'total_paid'     => round($items->sum('paid_amount'), 2),
                'total_debt'     => round($items->sum('remaining_amount'), 2),
                'total_debt_afn' => round($items->sum('remaining_amount_afn'), 2),
                'total_debt_usd' => round($items->sum('remaining_amount_usd'), 2),
                'invoices'       => $items->values(),
            ];
        })->values();

        $summary = [
            'total_customers' => $grouped->count(),
            'total_invoices'  => $sales->count(),
            'total_debt'      => round($sales->sum('remaining_amount'), 2),
            'total_debt_afn'  => round($sales->sum('remaining_amount_afn'), 2),
            'total_debt_usd'  => round($sales->sum('remaining_amount_usd'), 2),
        ];

        if ($request->boolean('flat')) {
            return response()->json([
                'summary' => $summary,
                'loans'   => $sales,
            ]);
        }

        return response()->json([
            'summary'   => $summary,
            'customers' => $grouped,
        ]);
    }

    /* =========================================================
     | ONE CUSTOMER LOANS — GET /api/loans/customers/{customer}
     * ========================================================= */
    public function singleCustomerLoans(Customer $customer, Request $request)
    {
        $query = Sale::with(['product'])
            ->where('customer_id', $customer->id)
            ->where('remaining_amount', '>', 0);

        if ($request->filled('from')) {
            $query->where('sale_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('sale_date', '<=', $request->to);
        }

        $loans = $query->latest()->get();

        return response()->json([
            'customer' => [
                'id'        => $customer->id,
                'name'      => $customer->name,
                'lastname'  => $customer->lastname,
                'phone'     => $customer->phone,
                'photo_url' => $customer->profile_photo_url,
            ],
            'summary' => [
                'invoices_count' => $loans->count(),
                'total_debt'     => round($loans->sum('remaining_amount'), 2),
                'total_debt_afn' => round($loans->sum('remaining_amount_afn'), 2),
                'total_debt_usd' => round($loans->sum('remaining_amount_usd'), 2),
            ],
            'loans' => $loans,
        ]);
    }

    /* =========================================================
     | SUPPLIER LOANS — GET /api/loans/suppliers
     | همه قرض‌های ما به تأمین‌کنندگان (از جدول products)
     * ========================================================= */
    public function supplierLoans(Request $request)
    {
        $query = Product::with('supplier')
            ->where('remaining_amount', '>', 0);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('barcode', 'like', "%{$s}%");
            });
        }

        $products = $query->latest()->get();

        // گروه‌بندی بر اساس تأمین‌کننده
        $grouped = $products->groupBy('supplier_id')->map(function ($items) {
            $first    = $items->first();
            $supplier = $first->supplier;

            return [
                'supplier_id'      => $first->supplier_id,
                'supplier_name'    => $supplier?->supplier_name,
                'supplier_phone'   => $supplier?->phone,
                'supplier_company' => $supplier?->company,
                'products_count'   => $items->count(),
                'total_purchases'  => round($items->sum('final_price'), 2),
                'total_paid'       => round($items->sum('paid_amount'), 2),
                'total_debt'       => round($items->sum('remaining_amount'), 2),
                'total_debt_afn'   => round($items->sum('remaining_amount_afn'), 2),
                'total_debt_usd'   => round($items->sum('remaining_amount_usd'), 2),
                'products'         => $items->values(),
            ];
        })->values();

        $summary = [
            'total_suppliers' => $grouped->count(),
            'total_products'  => $products->count(),
            'total_debt'      => round($products->sum('remaining_amount'), 2),
            'total_debt_afn'  => round($products->sum('remaining_amount_afn'), 2),
            'total_debt_usd'  => round($products->sum('remaining_amount_usd'), 2),
        ];

        if ($request->boolean('flat')) {
            return response()->json([
                'summary'  => $summary,
                'products' => $products,
            ]);
        }

        return response()->json([
            'summary'   => $summary,
            'suppliers' => $grouped,
        ]);
    }

    /* =========================================================
     | ONE SUPPLIER LOANS — GET /api/loans/suppliers/{supplier}
     * ========================================================= */
    public function singleSupplierLoans(Supplier $supplier, Request $request)
    {
        $query = Product::where('supplier_id', $supplier->id)
            ->where('remaining_amount', '>', 0);

        if ($request->filled('from')) {
            $query->where('stock_entry_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('stock_entry_date', '<=', $request->to);
        }

        $products = $query->latest()->get();

        return response()->json([
            'supplier' => [
                'id'      => $supplier->id,
                'name'    => $supplier->supplier_name,
                'phone'   => $supplier->phone,
                'company' => $supplier->company,
            ],
            'summary' => [
                'products_count' => $products->count(),
                'total_debt'     => round($products->sum('remaining_amount'), 2),
                'total_debt_afn' => round($products->sum('remaining_amount_afn'), 2),
                'total_debt_usd' => round($products->sum('remaining_amount_usd'), 2),
            ],
            'products' => $products,
        ]);
    }

    /* =========================================================
     | SUMMARY — GET /api/loans/summary
     | خلاصه کل قرض‌ها (مشتری + تأمین‌کننده)
     * ========================================================= */
    public function summary()
    {
        // مشتری
        $customerLoans = Sale::where('remaining_amount', '>', 0)->get();
        $customerSummary = [
            'total_customers' => $customerLoans->groupBy('customer_id')->count(),
            'total_invoices'  => $customerLoans->count(),
            'total_debt'      => round($customerLoans->sum('remaining_amount'), 2),
            'total_debt_afn'  => round($customerLoans->sum('remaining_amount_afn'), 2),
            'total_debt_usd'  => round($customerLoans->sum('remaining_amount_usd'), 2),
        ];

        // تأمین‌کننده
        $supplierLoans = Product::where('remaining_amount', '>', 0)->get();
        $supplierSummary = [
            'total_suppliers' => $supplierLoans->groupBy('supplier_id')->count(),
            'total_products'  => $supplierLoans->count(),
            'total_debt'      => round($supplierLoans->sum('remaining_amount'), 2),
            'total_debt_afn'  => round($supplierLoans->sum('remaining_amount_afn'), 2),
            'total_debt_usd'  => round($supplierLoans->sum('remaining_amount_usd'), 2),
        ];

        return response()->json([
            'customers' => $customerSummary,
            'suppliers' => $supplierSummary,
        ]);
    }
}