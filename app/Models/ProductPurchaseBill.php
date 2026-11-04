<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPurchaseBill extends Model
{
       protected $fillable = [
        'bill_number',
        'product_ids',
        'supplier_id',
    ];

    protected $casts = [
        'product_ids' => 'array',
    ];
    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

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
     * Build a bill snapshot from a single product (used when a product is created standalone).
     */
        public static function createFromProduct(Product $product): self
    {
        return self::create([
            'bill_number' => self::generateBillNumber(),
            'product_ids' => [$product->id],
            'supplier_id' => $product->supplier_id,
        ]);
    }

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

    /**
     * Only sync single-product bills. Multi-product bills are NOT touched here.
     */
    public function syncFromProduct(Product $product): self
    {
        $ids = $this->product_ids ?? [];

        if (!in_array($product->id, $ids)) {
            return $this;
        }

        if (count($ids) > 1) {
            return $this;
        }

        $this->fill([
            'product_name'        => $product->name,
            'category'            => $product->category,
            'barcode'             => $product->barcode,
            'photo'               => $product->photo,
            'module_name'         => $product->module_name,
            'sub_module_name'     => $product->sub_module_name,
            'sub_module_qty'      => $product->sub_module_qty ?: 1,
            'sub_sub_module_name' => $product->sub_sub_module_name,
            'sub_sub_module_qty'  => $product->sub_sub_module_qty ?: 1,
            'price_mode'          => $product->price_mode,
            'unit_price'          => $product->price,
            'currency'            => $product->currency,
            'exchange_rate'       => $product->exchange_rate,
            'final_qty'           => $product->final_qty,
            'final_price'         => $product->final_price,
            'final_price_afn'     => $product->final_price_afn,
            'final_price_usd'     => $product->final_price_usd,
            'paid_amount'         => $product->paid_amount,
            'paid_amount_afn'     => $product->paid_amount_afn,
            'paid_amount_usd'     => $product->paid_amount_usd,
            'remaining_amount'    => $product->remaining_amount,
            'remaining_amount_afn'=> $product->remaining_amount_afn,
            'remaining_amount_usd'=> $product->remaining_amount_usd,
            'payment_type'        => $product->payment_type,
        ]);

        return $this->save() ? $this : $this;
    }
}