<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPurchaseBill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /* =========================================================
     | INDEX — GET /api/products
     * ========================================================= */
    public function index(Request $request)
    {
        $query = Product::with('supplier');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('barcode', 'like', "%{$s}%")
                  ->orWhere('category', 'like', "%{$s}%");
            });
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // ✅ filter products that have NO supplier
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

        return $query->latest()->get();
    }

    /* =========================================================
     | SHOW — GET /api/products/{id}
     * ========================================================= */
    public function show(Product $product)
    {
        return $product->load('supplier');
    }

    /* =========================================================
     | STORE — POST /api/products
     * ========================================================= */
    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id'         => 'nullable|exists:suppliers,id',
            'name'                => 'required|string|max:255',
            'category'            => 'nullable|string|max:255',
            'barcode'             => 'nullable|string|max:100|unique:products,barcode',
            'photo'               => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'stock_entry_date'    => 'nullable|string|max:20',

            'module_name'         => 'required|string|max:100',
            'sub_module_name'     => 'nullable|string|max:100',
            'sub_module_qty'      => 'nullable|integer|min:1',
            'sub_sub_module_name' => 'nullable|string|max:100',
            'sub_sub_module_qty'  => 'nullable|integer|min:1',

            'price_mode'          => 'required|in:module,sub_module,sub_sub_module',
            'price'               => 'required|numeric|min:0',
            'qty'                 => 'required|integer|min:1',

            'currency'            => 'required|in:AFN,USD',
            'exchange_rate'       => 'required|numeric|min:0.0001',

            'payment_type'        => 'required|in:cash,loan_full,loan_partial',
            'paid_amount'         => 'nullable|numeric|min:0',

            'production_date'     => 'nullable|string|max:20',
            'expiry_date'         => 'nullable|string|max:20',
        ]);

        $data['sub_module_qty']     = $data['sub_module_qty']     ?? 1;
        $data['sub_sub_module_qty'] = $data['sub_sub_module_qty'] ?? 1;

        if ($data['price_mode'] === 'sub_module' && empty($data['sub_module_name'])) {
            return response()->json(['message' => 'sub_module_name لازم است'], 422);
        }
        if (
            $data['price_mode'] === 'sub_sub_module' &&
            (empty($data['sub_module_name']) || empty($data['sub_sub_module_name']))
        ) {
            return response()->json([
                'message' => 'sub_module_name و sub_sub_module_name لازم است'
            ], 422);
        }

        if ($data['payment_type'] === 'loan_partial' && empty($data['paid_amount'])) {
            return response()->json([
                'message' => 'مبلغ پرداخت‌شده برای قرض جزئی لازم است'
            ], 422);
        }

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('products', 'public');
        }

        $qty     = $data['qty'];
        $payment = [
            'payment_type' => $data['payment_type'],
            'paid_amount'  => $data['paid_amount'] ?? 0,
        ];

        unset($data['qty'], $data['paid_amount']);

        $product = DB::transaction(function () use ($data, $qty, $payment) {
            $product = Product::create($data);
            $product->applyFinalPrice($qty, $payment);

            ProductPurchaseBill::createFromProduct($product->fresh()->load('supplier'));

            return $product;
        });

        return response()->json($product->fresh()->load('supplier'), 201);
    }

    /* =========================================================
     | UPDATE — PUT /api/products/{id}
     * ========================================================= */
    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'supplier_id'         => 'sometimes|nullable|exists:suppliers,id',
            'name'                => 'sometimes|required|string|max:255',
            'category'            => 'sometimes|nullable|string|max:255',
            'barcode'             => 'sometimes|nullable|string|max:100|unique:products,barcode,' . $product->id,
            'photo'               => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'module_name'         => 'sometimes|required|string|max:100',
            'sub_module_name'     => 'sometimes|nullable|string|max:100',
            'sub_module_qty'      => 'sometimes|nullable|integer|min:1',
            'sub_sub_module_name' => 'sometimes|nullable|string|max:100',
            'sub_sub_module_qty'  => 'sometimes|nullable|integer|min:1',

            'price_mode'          => 'sometimes|required|in:module,sub_module,sub_sub_module',
            'price'               => 'sometimes|required|numeric|min:0',
            'qty'                 => 'sometimes|integer|min:1',
            'stock_entry_date'    => 'sometimes|nullable|string|max:20',

            'currency'            => 'sometimes|required|in:AFN,USD',
            'exchange_rate'       => 'sometimes|required|numeric|min:0.0001',

            'payment_type'        => 'sometimes|required|in:cash,loan_full,loan_partial',
            'paid_amount'         => 'sometimes|nullable|numeric|min:0',

            'production_date'     => 'sometimes|nullable|string|max:20',
            'expiry_date'         => 'sometimes|nullable|string|max:20',
        ]);

        if ($request->hasFile('photo')) {
            if ($product->photo && Storage::disk('public')->exists($product->photo)) {
                Storage::disk('public')->delete($product->photo);
            }
            $data['photo'] = $request->file('photo')->store('products', 'public');
        }

        $qty     = $data['qty'] ?? null;
        $payment = null;

        if (isset($data['payment_type']) || isset($data['paid_amount'])) {
            $payment = [
                'payment_type' => $data['payment_type'] ?? $product->payment_type,
                'paid_amount'  => $data['paid_amount']  ?? $product->paid_amount,
            ];
        }

        unset($data['qty'], $data['paid_amount']);

        DB::transaction(function () use ($product, $data, $qty, $payment, $request) {
            $product->update($data);

            $needsRecalc = $qty !== null
                || $payment !== null
                || $request->hasAny(['currency', 'exchange_rate', 'price']);

            if ($needsRecalc) {
                $useQty = $qty ?? (int) ($product->final_qty ?: 1);
                $product->applyFinalPrice($useQty, $payment ?? []);
            }

            $product->refresh()->load('supplier');
            $lastBill = ProductPurchaseBill::where('product_id', $product->id)
                        ->latest()
                        ->first();

            if ($lastBill) {
                $lastBill->syncFromProduct($product);
            }
        });

        return $product->fresh()->load('supplier');
    }

    /* =========================================================
     | DESTROY — DELETE /api/products/{id}
     * ========================================================= */
    public function destroy(Product $product)
    {
        if ($product->photo && Storage::disk('public')->exists($product->photo)) {
            Storage::disk('public')->delete($product->photo);
        }
        $product->delete();
        return response()->json(['message' => 'Product deleted']);
    }

    /* =========================================================
     | PAY — POST /api/products/{id}/pay
     * ========================================================= */
    public function pay(Request $request, Product $product)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($product->remaining_amount <= 0) {
            return response()->json(['message' => 'این محصول قرضی ندارد'], 422);
        }

        DB::transaction(function () use ($product, $data) {
            $newPaid = min(
                $product->paid_amount + $data['amount'],
                $product->final_price
            );

            $product->paid_amount      = round($newPaid, 2);
            $product->remaining_amount = round($product->final_price - $newPaid, 2);

            $rate = (float) ($product->exchange_rate ?: 1);
            $curr = strtoupper($product->currency ?: 'AFN');

            $paidConv      = Product::convertCurrency($product->paid_amount, $curr, $rate);
            $remainingConv = Product::convertCurrency($product->remaining_amount, $curr, $rate);

            $product->paid_amount_afn      = $paidConv['afn'];
            $product->paid_amount_usd      = $paidConv['usd'];
            $product->remaining_amount_afn = $remainingConv['afn'];
            $product->remaining_amount_usd = $remainingConv['usd'];

            $product->payment_type = $product->remaining_amount <= 0
                ? 'cash'
                : 'loan_partial';

            $product->save();

            $lastBill = ProductPurchaseBill::where('product_id', $product->id)
                        ->latest()
                        ->first();

            if ($lastBill) {
                $lastBill->syncFromProduct($product->fresh()->load('supplier'));
            }
        });

        return response()->json($product->fresh()->load('supplier'));
    }

    /* =========================================================
     | CALCULATE — POST /api/products/{id}/calculate
     * ========================================================= */
    public function calculate(Request $request, Product $product)
    {
        $data = $request->validate([
            'qty' => 'required|integer|min:1',
        ]);

        $calc = $product->calculatePrice($data['qty']);
        $rate = (float) ($product->exchange_rate ?: 1);
        $curr = strtoupper($product->currency ?: 'AFN');
        $conv = Product::convertCurrency($calc['final_price'], $curr, $rate);

        return response()->json([
            'product' => [
                'id'              => $product->id,
                'name'            => $product->name,
                'module_name'     => $product->module_name,
                'sub_module_name' => $product->sub_module_name,
                'currency'        => $curr,
                'exchange_rate'   => $rate,
            ],
            'calculation' => array_merge($calc, [
                'final_price_afn' => $conv['afn'],
                'final_price_usd' => $conv['usd'],
            ]),
        ]);
    }

    /* =========================================================
     | findByBarcode
     * ========================================================= */
    public function findByBarcode(Request $request)
    {
        $data = $request->validate([
            'barcode' => 'required|string',
        ]);

        $product = Product::with('supplier')
            ->where('barcode', $data['barcode'])
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json($product);
    }

    /* =========================================================
     | STOCK — GET /api/products/{product}/stock
     * ========================================================= */
    public function stock(Product $product)
    {
        $cartons = (int) $product->final_qty;
        $cans    = (int) $product->sub_module_final_qty;
        $pieces  = (int) $product->sub_sub_module_final_qty;

        return response()->json([
            'product' => [
                'id'   => $product->id,
                'name' => $product->name,
            ],
            'units' => [
                'module' => [
                    'name'  => $product->module_name,
                    'stock' => $cartons,
                ],
                'sub_module' => $product->sub_module_name ? [
                    'name'  => $product->sub_module_name,
                    'stock' => $cans,
                ] : null,
                'sub_sub_module' => $product->sub_sub_module_name ? [
                    'name'  => $product->sub_sub_module_name,
                    'stock' => $pieces,
                ] : null,
            ],
        ]);
    }

    /* =========================================================
     | STOCK ALL — GET /api/products-stock
     * ========================================================= */
    public function stockAll()
    {
        $products = Product::with('supplier')->get();

        $result = $products->map(function ($p) {
            $cartons = (int) $p->final_qty;
            $cans    = (int) $p->sub_module_final_qty;
            $pieces  = (int) $p->sub_sub_module_final_qty;

            $perCarton = max(1, (int) ($p->sub_module_qty ?: 1));
            $perCan    = max(1, (int) ($p->sub_sub_module_qty ?: 1));

            $packaging = "1 {$p->module_name}";
            if ($p->sub_module_name) {
                $packaging .= " = {$perCarton} {$p->sub_module_name}";
            }
            if ($p->sub_sub_module_name) {
                $packaging .= " = {$perCan} {$p->sub_sub_module_name}";
            }

            $modeText = match ($p->price_mode) {
                'module'         => "قیمت برای هر {$p->module_name}",
                'sub_module'     => "قیمت برای هر {$p->sub_module_name}",
                'sub_sub_module' => "قیمت برای هر {$p->sub_sub_module_name}",
                default          => '—',
            };

            $unitName = match ($p->price_mode) {
                'module'         => $p->module_name,
                'sub_module'     => $p->sub_module_name,
                'sub_sub_module' => $p->sub_sub_module_name,
                default          => null,
            };

            return [
                'id'        => $p->id,
                'name'      => $p->name,
                'category'  => $p->category,
                'barcode'   => $p->barcode,
                'photo'     => $p->photo,
                'photo_url' => $p->photo_url,
                'supplier'  => $p->supplier?->supplier_name,

                'price'      => (float) $p->price,
                'currency'   => $p->currency,

                'price_mode'      => $p->price_mode,
                'price_mode_text' => $modeText,
                'price_unit_name' => $unitName,

                'module'         => ['name' => $p->module_name,         'stock' => $cartons],
                'sub_module'     => $p->sub_module_name     ? ['name' => $p->sub_module_name,     'stock' => $cans]   : null,
                'sub_sub_module' => $p->sub_sub_module_name ? ['name' => $p->sub_sub_module_name, 'stock' => $pieces] : null,

                'packaging_description' => $packaging,
            ];
        });

        return response()->json($result);
    }

    /* =========================================================
 | ADD STOCK — POST /api/products/{id}/add-stock
 | Increments the existing product's stock quantities
 * ========================================================= */
public function addStock(Request $request, Product $product)
{
    $data = $request->validate([
        'add_module_qty'         => 'nullable|integer|min:0',
        'add_sub_module_qty'     => 'nullable|integer|min:0',
        'add_sub_sub_module_qty' => 'nullable|integer|min:0',
        'stock_entry_date'       => 'nullable|string|max:20',
    ]);

    $addMain = (int) ($data['add_module_qty']         ?? 0);
    $addSub  = (int) ($data['add_sub_module_qty']     ?? 0);
    $addSub2 = (int) ($data['add_sub_sub_module_qty'] ?? 0);

    if ($addMain === 0 && $addSub === 0 && $addSub2 === 0) {
        return response()->json(['message' => 'حداقل یک مقدار باید وارد شود'], 422);
    }

    DB::transaction(function () use ($product, $addMain, $addSub, $addSub2, $data) {
        // --- main unit ---
        if ($addMain > 0) {
            $product->final_qty = (int) $product->final_qty + $addMain;
        }

        // --- sub unit ---
        if ($addSub > 0 && $product->sub_module_name) {
            $product->sub_module_final_qty = (int) $product->sub_module_final_qty + $addSub;
        }

        // --- sub-sub unit ---
        if ($addSub2 > 0 && $product->sub_sub_module_name) {
            $product->sub_sub_module_final_qty = (int) $product->sub_sub_module_final_qty + $addSub2;
        }

        // optionally update last stock entry date
        if (!empty($data['stock_entry_date'])) {
            $product->stock_entry_date = $data['stock_entry_date'];
        }

        $product->save();

        // sync latest bill if you have it
        $lastBill = ProductPurchaseBill::where('product_id', $product->id)
                    ->latest()
                    ->first();
        if ($lastBill) {
            $lastBill->syncFromProduct($product->fresh()->load('supplier'));
        }
    });

    return response()->json($product->fresh()->load('supplier'));
}
}