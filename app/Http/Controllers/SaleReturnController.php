<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleReturnController extends Controller
{
    /* =========================================================
     | LIST ALL RETURNS
     * ========================================================= */
    public function index(Request $request)
    {
        $query = SaleReturn::with(['sale', 'product', 'customer']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('return_number', 'like', "%{$s}%")
                  ->orWhere('invoice_number', 'like', "%{$s}%")
                  ->orWhere('product_name', 'like', "%{$s}%")
                  ->orWhere('customer_name', 'like', "%{$s}%");
            });
        }

        return $query->latest()->get();
    }

    public function show(SaleReturn $saleReturn)
    {
        return $saleReturn->load(['sale', 'product', 'customer']);
    }

    /* =========================================================
     | PREVIEW — how much stock / capital / profit is affected
     * ========================================================= */
    public function preview(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'return_unit' => 'required|in:module,sub_module,sub_sub_module',
            'qty_returned'=> 'required|integer|min:1',
        ]);

        $product = $sale->product;
        if (!$product) {
            return response()->json(['message' => 'محصول این فروش یافت نشد'], 422);
        }

        $result = $this->computeReturn($sale, $product, $data['return_unit'], (int) $data['qty_returned']);

        if ($result['error']) {
            return response()->json(['message' => $result['error']], 422);
        }

        // current stock & remaining stock simulation
        $stock = ProductStock::firstOrCreate(['product_id' => $product->id]);
        $stock->setRelation('product', $product);

        $currentPieces = (int) $stock->getCurrentPiecesCount();

        $tempStock = new ProductStock();
        $tempStock->product_id = $product->id;
        $tempStock->setRelation('product', $product);
        $tempStock->syncFromPieces($currentPieces + $result['pieces_returned']);

        return response()->json([
            'sale' => [
                'id'             => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'product_name'   => $sale->product_name,
                'sale_unit'      => $sale->sale_unit,
                'qty_sold'       => $sale->qty_sold,
                'qty_remaining'  => $result['qty_remaining_in_sale'],
                'unit_price'     => $sale->unit_price,
                'currency'       => $sale->currency,
                'exchange_rate'  => $sale->exchange_rate,
                'payment_type'   => $sale->payment_type,
                'remaining_debt' => $sale->remaining_amount,
            ],
            'return' => [
                'unit'         => $data['return_unit'],
                'unit_name'    => $result['unit_name'],
                'qty'          => (int) $data['qty_returned'],
                'cartons'      => $result['cartons_returned'],
                'cans'         => $result['sub_units_returned'],
                'pieces'       => $result['pieces_returned'],
            ],
            'money' => [
                'refund_total'   => $result['total_price'],
                'capital_back'   => $result['cost_price'],
                'profit_lost'    => $result['profit_reversed'],
                'refund_afn'     => $result['total_price_afn'],
                'refund_usd'     => $result['total_price_usd'],
                'capital_afn'    => $result['cost_price_afn'],
                'capital_usd'    => $result['cost_price_usd'],
            ],
            'current_stock' => [
                'module'         => $stock->module_stock ?? 0,
                'sub_module'     => $stock->sub_module_stock ?? 0,
                'sub_sub_module' => $stock->sub_sub_module_stock ?? 0,
            ],
            'remaining_stock' => [
                'module'         => $tempStock->module_stock ?? 0,
                'sub_module'     => $tempStock->sub_module_stock ?? 0,
                'sub_sub_module' => $tempStock->sub_sub_module_stock ?? 0,
            ],
        ]);
    }

    /* =========================================================
     | STORE — perform the return
     * ========================================================= */
    public function store(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'return_unit'  => 'required|in:module,sub_module,sub_sub_module',
            'qty_returned' => 'required|integer|min:1',
            'refund_type'  => 'required|in:cash,reduce_debt,exchange',
            'refund_amount'=> 'nullable|numeric|min:0',
            'return_date'  => 'nullable|string|max:20',
            'note'         => 'nullable|string',
        ]);

        $product = $sale->product;
        if (!$product) {
            return response()->json(['message' => 'محصول این فروش یافت نشد'], 422);
        }

        $calc = $this->computeReturn($sale, $product, $data['return_unit'], (int) $data['qty_returned']);

        if ($calc['error']) {
            return response()->json(['message' => $calc['error']], 422);
        }

        // 👈 Persian date — sent as-is from frontend (e.g. "1405-08-21")
        $returnDate = $data['return_date'] ?? null;

        $saleReturn = DB::transaction(function () use ($sale, $product, $data, $calc, $returnDate) {
            /* --------------------------------------------------
             | 1. ADD STOCK BACK — pieces → re-pack into cartons
             * -------------------------------------------------- */
            $stock = ProductStock::firstOrCreate(['product_id' => $product->id]);
            $stock->setRelation('product', $product);

            $currentPieces = (int) $stock->getCurrentPiecesCount();
            $newPieces = $currentPieces + $calc['pieces_returned'];
            $stock->syncFromPieces($newPieces);
            $stock->save();

            /* --------------------------------------------------
             | 2. HANDLE MONEY / DEBT
             * -------------------------------------------------- */
            $refundType   = $data['refund_type'];
            $totalPrice   = $calc['total_price'];
            $debtReduced  = 0.0;
            $refundAmount = 0.0;

            if ($sale->remaining_amount > 0) {
                $debtReduced = min($totalPrice, $sale->remaining_amount);

                $newRemaining = round($sale->remaining_amount - $debtReduced, 2);
                $newPaid      = round($sale->paid_amount + $debtReduced, 2);

                $rate = (float) ($sale->exchange_rate ?: 1);
                $curr = strtoupper($sale->currency ?: 'AFN');

                $paidConv      = Product::convertCurrency($newPaid, $curr, $rate);
                $remainingConv = Product::convertCurrency($newRemaining, $curr, $rate);

                $sale->paid_amount          = $newPaid;
                $sale->paid_amount_afn      = $paidConv['afn'];
                $sale->paid_amount_usd      = $paidConv['usd'];
                $sale->remaining_amount     = $newRemaining;
                $sale->remaining_amount_afn = $remainingConv['afn'];
                $sale->remaining_amount_usd = $remainingConv['usd'];

                if ($newRemaining <= 0) {
                    $sale->payment_type = 'cash';
                }
                $sale->save();

                $refundAmount = round($totalPrice - $debtReduced, 2);
            } else {
                $refundAmount = $totalPrice;
            }

            if ($refundType === 'reduce_debt') {
                $refundAmount = 0;
            }

            /* --------------------------------------------------
             | 3. SAVE RETURN RECORD
             * -------------------------------------------------- */
            $rate = (float) ($sale->exchange_rate ?: 1);
            $curr = strtoupper($sale->currency ?: 'AFN');

            $refundConv = Product::convertCurrency($refundAmount, $curr, $rate);

            return SaleReturn::create([
                'return_number'      => SaleReturn::generateReturnNumber(),
                'sale_id'            => $sale->id,
                'invoice_number'     => $sale->invoice_number,
                'product_id'         => $product->id,
                'product_name'       => $product->name,
                'customer_id'        => $sale->customer_id,
                'customer_name'      => $sale->customer_name,

                'return_unit'        => $data['return_unit'],
                'return_unit_name'   => $calc['unit_name'],
                'qty_returned'       => (int) $data['qty_returned'],

                'pieces_returned'    => $calc['pieces_returned'],
                'cartons_returned'   => $calc['cartons_returned'],
                'sub_units_returned' => $calc['sub_units_returned'],

                'unit_price'         => $calc['unit_price'],
                'total_price'        => $calc['total_price'],
                'cost_price'         => $calc['cost_price'],
                'profit_reversed'    => $calc['profit_reversed'],

                'currency'           => $curr,
                'exchange_rate'      => $rate,

                'total_price_afn'    => $calc['total_price_afn'],
                'total_price_usd'    => $calc['total_price_usd'],
                'cost_price_afn'     => $calc['cost_price_afn'],
                'cost_price_usd'     => $calc['cost_price_usd'],

                'refund_type'        => $refundType,
                'refund_amount'      => $refundAmount,
                'refund_amount_afn'  => $refundConv['afn'],
                'refund_amount_usd'  => $refundConv['usd'],
                'debt_reduced'       => $debtReduced,

                'return_date'        => $returnDate,   // 👈 Persian date string
                'note'               => $data['note'] ?? null,
            ]);
        });

        return response()->json($saleReturn->load(['sale', 'product', 'customer']), 201);
    }

    /* =========================================================
     | DESTROY — undo a return
     * ========================================================= */
    public function destroy(SaleReturn $saleReturn)
    {
        DB::transaction(function () use ($saleReturn) {
            $product = $saleReturn->product;

            if ($product) {
                $stock = ProductStock::firstOrCreate(['product_id' => $product->id]);
                $stock->setRelation('product', $product);

                $currentPieces = (int) $stock->getCurrentPiecesCount();
                $newPieces = max(0, $currentPieces - $saleReturn->pieces_returned);
                $stock->syncFromPieces($newPieces);
                $stock->save();
            }

            $sale = $saleReturn->sale;
            if ($sale && $saleReturn->debt_reduced > 0) {
                $sale->paid_amount      = max(0, $sale->paid_amount - $saleReturn->debt_reduced);
                $sale->remaining_amount = $sale->remaining_amount + $saleReturn->debt_reduced;

                $rate = (float) ($sale->exchange_rate ?: 1);
                $curr = strtoupper($sale->currency ?: 'AFN');

                $paidConv      = Product::convertCurrency($sale->paid_amount, $curr, $rate);
                $remainingConv = Product::convertCurrency($sale->remaining_amount, $curr, $rate);

                $sale->paid_amount_afn      = $paidConv['afn'];
                $sale->paid_amount_usd      = $paidConv['usd'];
                $sale->remaining_amount_afn = $remainingConv['afn'];
                $sale->remaining_amount_usd = $remainingConv['usd'];
                $sale->payment_type         = 'loan_partial';
                $sale->save();
            }

            $saleReturn->delete();
        });

        return response()->json(['message' => 'Return deleted']);
    }

    /* =========================================================
     | CORE CALCULATOR
     * ========================================================= */
    private function computeReturn(Sale $sale, Product $product, string $unit, int $qty): array
    {
        $perCarton = $product->cansPerCarton();
        $perCan    = $product->piecesPerCan();

        switch ($unit) {
            case 'module':
                $pieces = $qty * $perCarton * $perCan;
                $unitName = $product->module_name;
                break;
            case 'sub_module':
                $pieces = $product->sub_sub_module_name
                    ? $qty * $perCan
                    : $qty;
                $unitName = $product->sub_module_name;
                break;
            case 'sub_sub_module':
            default:
                $pieces = $qty;
                $unitName = $product->sub_sub_module_name;
                break;
        }

        $maxQty = $this->maxReturnableQty($sale, $product, $unit);
        if ($qty > $maxQty) {
            return ['error' => "حداکثر مقدار قابل برگشت برای این واحد {$maxQty} است"];
        }

        $cartons = intdiv($pieces, $perCarton * $perCan);
        $rem     = $pieces % ($perCarton * $perCan);
        $cans    = $product->sub_sub_module_name ? intdiv($rem, $perCan) : $rem;

        $unitPrice  = (float) $sale->unit_price;
        $totalPrice = round($qty * $unitPrice, 2);

        $piecesPerBillableUnit = match ($product->price_mode) {
            'module'         => $perCarton * $perCan,
            'sub_module'     => $perCan,
            'sub_sub_module' => 1,
            default          => 1,
        };

        $costPerPiece = (float) $product->price / max(1, $piecesPerBillableUnit);

        $productRate = (float) ($product->exchange_rate ?: 1);
        $productCurr = strtoupper($product->currency ?: 'AFN');
        if ($productCurr === 'USD') {
            $costPerPiece *= $productRate;
        }

        $costPrice = round($costPerPiece * $pieces, 2);
        $profitReversed = round($totalPrice - $costPrice, 2);

        $rate = (float) ($sale->exchange_rate ?: 1);
        $curr = strtoupper($sale->currency ?: 'AFN');

        $totalConv = Product::convertCurrency($totalPrice, $curr, $rate);
        $costConv  = Product::convertCurrency($costPrice,  $curr, $rate);

        return [
            'error'              => null,
            'unit_name'          => $unitName,
            'pieces_returned'    => $pieces,
            'cartons_returned'   => $cartons,
            'sub_units_returned' => $cans,
            'unit_price'         => $unitPrice,
            'total_price'        => $totalPrice,
            'cost_price'         => $costPrice,
            'profit_reversed'    => $profitReversed,
            'total_price_afn'    => $totalConv['afn'],
            'total_price_usd'    => $totalConv['usd'],
            'cost_price_afn'     => $costConv['afn'],
            'cost_price_usd'     => $costConv['usd'],
            'qty_remaining_in_sale' => $maxQty,
        ];
    }

    private function maxReturnableQty(Sale $sale, Product $product, string $unit): int
    {
        $soldInUnit = $this->convertSoldQtyToUnit($sale, $product, $unit);

        $returnedInUnit = (int) SaleReturn::where('sale_id', $sale->id)
            ->where('return_unit', $unit)
            ->sum('qty_returned');

        return max(0, $soldInUnit - $returnedInUnit);
    }

    private function convertSoldQtyToUnit(Sale $sale, Product $product, string $unit): int
    {
        $perCarton = $product->cansPerCarton();
        $perCan    = $product->piecesPerCan();
        $soldQty   = (int) $sale->qty_sold;

        $soldPieces = match ($sale->sale_unit) {
            'module'         => $soldQty * $perCarton * $perCan,
            'sub_module'     => $product->sub_sub_module_name ? $soldQty * $perCan : $soldQty,
            'sub_sub_module' => $soldQty,
            default          => $soldQty,
        };

        return match ($unit) {
            'module'         => intdiv($soldPieces, $perCarton * $perCan),
            'sub_module'     => $product->sub_sub_module_name ? intdiv($soldPieces, $perCarton * $perCan) : $soldPieces,
            'sub_sub_module' => $soldPieces,
            default          => $soldPieces,
        };
    }
}