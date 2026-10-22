<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    /* =========================================================
     | INDEX — GET /api/sales
     * ========================================================= */
    public function index(Request $request)
    {
        $query = Sale::with(['customer', 'product']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                  ->orWhere('customer_name', 'like', "%{$s}%")
                  ->orWhere('product_name', 'like', "%{$s}%");
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

        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }

        if ($request->filled('from')) {
            $query->where('sale_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('sale_date', '<=', $request->to);
        }

        return $query->latest()->get();
    }

    /* =========================================================
     | SHOW — GET /api/sales/{id}
     * ========================================================= */
    public function show(Sale $sale)
    {
        return $sale->load(['customer', 'product']);
    }

    /* =========================================================
     | STORE — POST /api/sales
     * ========================================================= */
    public function store(Request $request)
    {
        // 👇 convert empty supplier/customer to null before validation
        if ($request->input('customer_id') === '') {
            $request->merge(['customer_id' => null]);
        }

        $data = $request->validate([
            'customer_id'   => 'nullable|exists:customers,id',
            'product_id'    => 'required|exists:products,id',

            'sale_unit'     => 'required|in:module,sub_module,sub_sub_module',
            'qty_sold'      => 'required|integer|min:1',

            'unit_price'    => 'required|numeric|min:0',
            'currency'      => 'required|in:AFN,USD',
            'exchange_rate' => 'required|numeric|min:0.0001',

            'payment_type'  => 'required|in:cash,loan_full,loan_partial',
            'paid_amount'   => 'nullable|numeric|min:0',

            'sale_date'     => 'nullable|string|max:20',
            'note'          => 'nullable|string',
        ]);

        $product  = Product::with('supplier')->findOrFail($data['product_id']);
        $customer = !empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;

        // 1) بررسی موجودی
        $this->checkStock($product, $data['sale_unit'], $data['qty_sold']);

        // 2) تبدیل qty به قطعه
        $deduction = $this->calculateDeduction(
            $product,
            $data['sale_unit'],
            $data['qty_sold']
        );

        // 3) قیمت
        $qty        = $data['qty_sold'];
        $unitPrice  = (float) $data['unit_price'];
        $totalPrice = round($qty * $unitPrice, 2);

        $rate = (float) $data['exchange_rate'];
        $curr = strtoupper($data['currency']);

        $totalConv = Product::convertCurrency($totalPrice, $curr, $rate);

        // 4) پرداخت
        switch ($data['payment_type']) {
            case 'loan_full':
                $paid = 0;
                break;
            case 'loan_partial':
                $paid = max(0, min((float) ($data['paid_amount'] ?? 0), $totalPrice));
                break;
            case 'cash':
            default:
                $paid = $totalPrice;
        }
        $remaining = round($totalPrice - $paid, 2);

        $paidConv      = Product::convertCurrency($paid, $curr, $rate);
        $remainingConv = Product::convertCurrency($remaining, $curr, $rate);

        // 5) هزینه خرید
        $costPrice = $this->calculateCost($product, $deduction);

        // 6) سود
        $profit = round($totalPrice - $costPrice, 2);

        // 7) نام واحد فروش
        $saleUnitName = match ($data['sale_unit']) {
            'module'         => $product->module_name,
            'sub_module'     => $product->sub_module_name,
            'sub_sub_module' => $product->sub_sub_module_name,
        };

        // 8) ثبت اتمیک
        $sale = DB::transaction(function () use (
            $product, $customer, $data, $deduction,
            $saleUnitName, $totalPrice, $totalConv, $curr, $rate,
            $paid, $paidConv, $remaining, $remainingConv,
            $costPrice, $profit
        ) {
            // کسر از موجودی
            $product->removePiecesFromStock($deduction['pieces_used']);
            $product->save();

            return Sale::create([
                'invoice_number' => Sale::generateInvoiceNumber(),
                'sale_date'      => $data['sale_date'] ?? null,

                'customer_id'   => $customer?->id,
                'customer_name' => $customer
                    ? trim("{$customer->name} {$customer->lastname}")
                    : 'مشتری نقدی',

                'product_id'    => $product->id,
                'product_name'  => $product->name,

                'sale_unit'      => $data['sale_unit'],
                'sale_unit_name' => $saleUnitName,
                'qty_sold'       => $data['qty_sold'],

                'cartons_used'    => $deduction['cartons_used'],
                'sub_units_used'  => $deduction['sub_units_used'],
                'pieces_used'     => $deduction['pieces_used'],

                'module_name'         => $product->module_name,
                'sub_module_name'     => $product->sub_module_name,
                'sub_module_qty'      => $product->sub_module_qty ?: 1,
                'sub_sub_module_name' => $product->sub_sub_module_name,
                'sub_sub_module_qty'  => $product->sub_sub_module_qty ?: 1,

                'unit_price'    => (float) $data['unit_price'],
                'currency'      => $curr,
                'exchange_rate' => $rate,

                'total_price'      => $totalPrice,
                'total_price_afn'  => $totalConv['afn'],
                'total_price_usd'  => $totalConv['usd'],

                'payment_type' => $data['payment_type'],

                'paid_amount'          => $paid,
                'paid_amount_afn'      => $paidConv['afn'],
                'paid_amount_usd'      => $paidConv['usd'],

                'remaining_amount'     => $remaining,
                'remaining_amount_afn' => $remainingConv['afn'],
                'remaining_amount_usd' => $remainingConv['usd'],

                'cost_price' => $costPrice,
                'profit'     => $profit,

                'note' => $data['note'] ?? null,
            ]);
        });

        // ✅ ساخت فاکتور فروش
        SaleInvoice::createFromSale($sale);

        return response()->json($sale->load(['customer', 'product']), 201);
    }

    /* =========================================================
     | DESTROY — DELETE /api/sales/{id}
     * ========================================================= */
    public function destroy(Sale $sale)
    {
        DB::transaction(function () use ($sale) {
            if ($sale->product) {
                $sale->product->addPiecesToStock((int) $sale->pieces_used);
                $sale->product->save();
            }

            $sale->delete();
        });

        return response()->json(['message' => 'Sale deleted']);
    }

    /* =========================================================
     | PAY — POST /api/sales/{id}/pay
     * ========================================================= */
    public function pay(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($sale->remaining_amount <= 0) {
            return response()->json(['message' => 'این فروش قرضی ندارد'], 422);
        }

        $sale->applyPayment((float) $data['amount']);

        $invoice = SaleInvoice::where('sale_id', $sale->id)->first();
        if ($invoice) {
            $invoice->syncFromSale($sale->fresh());
        }

        return response()->json($sale->fresh()->load(['customer', 'product']));
    }

    /* =========================================================
     | PREVIEW — POST /api/sales/preview
     * ========================================================= */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sale_unit'  => 'required|in:module,sub_module,sub_sub_module',
            'qty_sold'   => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($data['product_id']);

        $this->checkStock($product, $data['sale_unit'], $data['qty_sold']);

        $deduction = $this->calculateDeduction(
            $product,
            $data['sale_unit'],
            $data['qty_sold']
        );

        $perCarton = $product->cansPerCarton();
        $perCan    = $product->piecesPerCan();

        $currentPieces   = $product->totalPiecesInStock();
        $remainingPieces = max(0, $currentPieces - $deduction['pieces_used']);

        $currentBreakdown   = Sale::breakdownFromPieces($currentPieces,   $perCarton, $perCan);
        $remainingBreakdown = Sale::breakdownFromPieces($remainingPieces, $perCarton, $perCan);

        $cartonSize         = $perCarton * $perCan;
        $cartonsSoldPercent = $cartonSize > 0
            ? round(($deduction['pieces_used'] / $cartonSize) * 100, 2)
            : 0;

        return response()->json([
            'product' => [
                'id'                  => $product->id,
                'name'                => $product->name,
                'module_name'         => $product->module_name,
                'sub_module_name'     => $product->sub_module_name,
                'sub_module_qty'      => $perCarton,
                'sub_sub_module_name' => $product->sub_sub_module_name,
                'sub_sub_module_qty'  => $perCan,
            ],
            'sold' => [
                'unit'   => $data['sale_unit'],
                'qty'    => $data['qty_sold'],
                'deduct' => [
                    'cartons' => $deduction['cartons_used'],
                    'cans'    => $deduction['sub_units_used'],
                    'pieces'  => $deduction['pieces_used'],
                ],
            ],
            'current_stock' => [
                'pieces'    => $currentPieces,
                'breakdown' => $currentBreakdown,
            ],
            'remaining_stock' => [
                'pieces'    => $remainingPieces,
                'breakdown' => $remainingBreakdown,
            ],
            'cartons_sold_percent' => $cartonsSoldPercent,
        ]);
    }

    /* =========================================================
     | HELPERS
     * ========================================================= */

    private function calculateDeduction(Product $product, string $unit, int $qty): array
    {
        $perCarton = $product->cansPerCarton();
        $perCan    = $product->piecesPerCan();

        switch ($unit) {
            case 'module':
                $pieces = $qty * $perCarton * $perCan;
                break;

            case 'sub_module':
                $pieces = $product->sub_sub_module_name
                    ? $qty * $perCan
                    : $qty;
                break;

            case 'sub_sub_module':
            default:
                $pieces = $qty;
        }

        $cartonsUsed = intdiv($pieces, $perCarton * $perCan);
        $remainder   = $pieces % ($perCarton * $perCan);

        $cansUsed = $product->sub_sub_module_name
            ? intdiv($remainder, $perCan)
            : $remainder;

        return [
            'cartons_used'   => $cartonsUsed,
            'sub_units_used' => $cansUsed,
            'pieces_used'    => $pieces,
        ];
    }

    private function checkStock(Product $product, string $unit, int $qty): void
    {
        $deduct = $this->calculateDeduction($product, $unit, $qty);

        $availablePieces = $product->totalPiecesInStock();

        if ($deduct['pieces_used'] > $availablePieces) {
            abort(response()->json([
                'message' => "موجودی کافی نیست. موجودی فعلی: {$product->final_qty} {$product->module_name} و {$product->sub_module_final_qty} {$product->sub_module_name} ({$availablePieces} قطعه)، درخواستی: {$deduct['pieces_used']} قطعه",
            ], 422));
        }
    }

    private function calculateCost(Product $product, array $deduction): float
    {
        $perCarton = $product->cansPerCarton();
        $perCan    = $product->piecesPerCan();

        $piecesPerBillableUnit = match ($product->price_mode) {
            'module'         => $perCarton * $perCan,
            'sub_module'     => $perCan,
            'sub_sub_module' => 1,
            default          => 1,
        };

        $costPerPiece = (float) $product->price / max(1, $piecesPerBillableUnit);

        $rate = (float) ($product->exchange_rate ?: 1);
        $curr = strtoupper($product->currency ?: 'AFN');

        if ($curr === 'USD') {
            $costPerPiece *= $rate;
        }

        return round($costPerPiece * $deduction['pieces_used'], 2);
    }
}