<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPurchaseBill extends Model
{
    protected $fillable = [
        'bill_number',
        'product_id', 'supplier_id',

        // product snapshot
        'product_name', 'category', 'barcode', 'photo',
        'module_name', 'sub_module_name', 'sub_module_qty',
        'sub_sub_module_name', 'sub_sub_module_qty',

        // price snapshot
        'price_mode', 'unit_price', 'currency', 'exchange_rate',
        'qty', 'final_qty',
        'final_price', 'final_price_afn', 'final_price_usd',

        // payment snapshot
        'payment_type',
        'paid_amount', 'paid_amount_afn', 'paid_amount_usd',
        'remaining_amount', 'remaining_amount_afn', 'remaining_amount_usd',

        // supplier snapshot
        'supplier_name', 'supplier_phone', 'supplier_address',

        // dates
        'production_date', 'expiry_date',
    ];

    protected $casts = [
        'sub_module_qty'         => 'integer',
        'sub_sub_module_qty'     => 'integer',
        'qty'                    => 'integer',
        'final_qty'              => 'integer',

        'unit_price'             => 'float',
        'exchange_rate'          => 'float',

        'final_price'            => 'float',
        'final_price_afn'        => 'float',
        'final_price_usd'        => 'float',

        'paid_amount'            => 'float',
        'paid_amount_afn'        => 'float',
        'paid_amount_usd'        => 'float',

        'remaining_amount'       => 'float',
        'remaining_amount_afn'   => 'float',
        'remaining_amount_usd'   => 'float',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * تولید شماره فاکتور منحصر به فرد
     */
    public static function generateBillNumber(): string
    {
        $prefix = 'PB-' . now()->format('Ymd') . '-';
        $last = self::where('bill_number', 'like', $prefix . '%')
                    ->orderByDesc('id')
                    ->value('bill_number');

        $next = 1;
        if ($last) {
            $parts = explode('-', $last);
            $next = ((int) end($parts)) + 1;
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * ساخت صورتحساب از یک محصول
     */
    public static function createFromProduct(Product $product): self
    {
        return self::create([
            'bill_number' => self::generateBillNumber(),

            'product_id'  => $product->id,
            'supplier_id' => $product->supplier_id,

            // product snapshot
            'product_name'        => $product->name,
            'category'            => $product->category,
            'barcode'             => $product->barcode,
            'photo'               => $product->photo,
            'module_name'         => $product->module_name,
            'sub_module_name'     => $product->sub_module_name,
            'sub_module_qty'      => $product->sub_module_qty ?: 1,
            'sub_sub_module_name' => $product->sub_sub_module_name,
            'sub_sub_module_qty'  => $product->sub_sub_module_qty ?: 1,

            // price snapshot
            'price_mode'          => $product->price_mode,
            'unit_price'          => $product->price,
            'currency'            => $product->currency,
            'exchange_rate'       => $product->exchange_rate,
            'qty'                 => self::computeQty($product),
            'final_qty'           => $product->final_qty,
            'final_price'         => $product->final_price,
            'final_price_afn'     => $product->final_price_afn,
            'final_price_usd'     => $product->final_price_usd,

            // payment snapshot
            'payment_type'            => $product->payment_type,
            'paid_amount'             => $product->paid_amount,
            'paid_amount_afn'         => $product->paid_amount_afn,
            'paid_amount_usd'         => $product->paid_amount_usd,
            'remaining_amount'        => $product->remaining_amount,
            'remaining_amount_afn'    => $product->remaining_amount_afn,
            'remaining_amount_usd'    => $product->remaining_amount_usd,

            // supplier snapshot
            'supplier_name'    => $product->supplier?->supplier_name,
            'supplier_phone'   => $product->supplier?->phone ?? null,
            'supplier_address' => $product->supplier?->address ?? null,

            // dates
            'production_date' => $product->production_date,
            'expiry_date'     => $product->expiry_date,
        ]);
    }

    /**
     * محاسبه qty از final_qty + mode
     */
    private static function computeQty(Product $product): int
    {
        $perModule = max(1, (int) ($product->sub_module_qty ?: 1));
        $perCan    = max(1, (int) ($product->sub_sub_module_qty ?: 1));

        $billablePerModule = match ($product->price_mode) {
            'sub_sub_module' => $perModule * $perCan,
            'sub_module'     => $perModule,
            default          => 1,
        };

        return max(1, (int) round(($product->final_qty ?: 1) / $billablePerModule));
    }
}