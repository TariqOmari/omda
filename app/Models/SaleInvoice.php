<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleInvoice extends Model
{
    protected $fillable = [
        'sale_id', 'invoice_number',

        'customer_id', 'customer_name', 'customer_lastname',
        'customer_phone', 'customer_address',

        'product_id', 'product_name', 'product_category',
        'product_barcode', 'product_photo',

        'sale_unit', 'sale_unit_name', 'qty_sold',
        'cartons_used', 'sub_units_used', 'pieces_used',

        'module_name', 'sub_module_name', 'sub_module_qty',
        'sub_sub_module_name', 'sub_sub_module_qty',

        'unit_price', 'currency', 'exchange_rate',
        'total_price', 'total_price_afn', 'total_price_usd',

        'payment_type',
        'paid_amount', 'paid_amount_afn', 'paid_amount_usd',
        'remaining_amount', 'remaining_amount_afn', 'remaining_amount_usd',

        'cost_price', 'profit',

        'sale_date', 'note',

        'supplier_id', 'supplier_name',

        'status',
    ];

    protected $casts = [
        'qty_sold'             => 'integer',
        'cartons_used'         => 'integer',
        'sub_units_used'       => 'integer',
        'pieces_used'          => 'integer',
        'sub_module_qty'       => 'integer',
        'sub_sub_module_qty'   => 'integer',

        'unit_price'           => 'float',
        'exchange_rate'        => 'float',
        'total_price'          => 'float',
        'total_price_afn'      => 'float',
        'total_price_usd'      => 'float',

        'paid_amount'          => 'float',
        'paid_amount_afn'      => 'float',
        'paid_amount_usd'      => 'float',
        'remaining_amount'     => 'float',
        'remaining_amount_afn' => 'float',
        'remaining_amount_usd' => 'float',

        'cost_price'           => 'float',
        'profit'               => 'float',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /* =========================================================
     | FACTORY — ساخت فاکتور از یک Sale
     * ========================================================= */

    public static function createFromSale(Sale $sale): self
    {
        $sale->loadMissing(['customer', 'product.supplier']);

        $customer = $sale->customer;
        $product  = $sale->product;
        $supplier = $product?->supplier;

        return self::create([
            'sale_id'        => $sale->id,
            'invoice_number' => $sale->invoice_number,

            // مشتری — اگه نبود، "مشتری نقدی"
            'customer_id'       => $sale->customer_id,
            'customer_name'     => $customer?->name
                                    ?? $sale->customer_name
                                    ?? 'مشتری نقدی',
            'customer_lastname' => $customer?->lastname ?? null,
            'customer_phone'    => $customer?->phone ?? null,
            'customer_address'  => $customer?->address ?? null,

            // محصول
            'product_id'       => $sale->product_id,
            'product_name'     => $sale->product_name,
            'product_category' => $product?->category,
            'product_barcode'  => $product?->barcode,
            'product_photo'    => $product?->photo,

            // واحدها
            'sale_unit'      => $sale->sale_unit,
            'sale_unit_name' => $sale->sale_unit_name,
            'qty_sold'       => $sale->qty_sold,

            'cartons_used'   => $sale->cartons_used,
            'sub_units_used' => $sale->sub_units_used,
            'pieces_used'    => $sale->pieces_used,

            'module_name'         => $sale->module_name,
            'sub_module_name'     => $sale->sub_module_name,
            'sub_module_qty'      => $sale->sub_module_qty,
            'sub_sub_module_name' => $sale->sub_sub_module_name,
            'sub_sub_module_qty'  => $sale->sub_sub_module_qty,

            // قیمت‌ها
            'unit_price'      => $sale->unit_price,
            'currency'        => $sale->currency,
            'exchange_rate'   => $sale->exchange_rate,

            'total_price'     => $sale->total_price,
            'total_price_afn' => $sale->total_price_afn,
            'total_price_usd' => $sale->total_price_usd,

            // پرداخت
            'payment_type' => $sale->payment_type,

            'paid_amount'          => $sale->paid_amount,
            'paid_amount_afn'      => $sale->paid_amount_afn,
            'paid_amount_usd'      => $sale->paid_amount_usd,

            'remaining_amount'     => $sale->remaining_amount,
            'remaining_amount_afn' => $sale->remaining_amount_afn,
            'remaining_amount_usd' => $sale->remaining_amount_usd,

            // سود
            'cost_price' => $sale->cost_price,
            'profit'     => $sale->profit,

            // اضافی
            'sale_date' => $sale->sale_date,
            'note'      => $sale->note,

            // تأمین‌کننده — اگه نبود، null
            'supplier_id'   => $supplier?->id,
            'supplier_name' => $supplier?->supplier_name,

            'status' => 'active',
        ]);
    }

    /* =========================================================
     | SYNC — همگام‌سازی بعد از پرداخت
     * ========================================================= */

    public function syncFromSale(Sale $sale): self
    {
        $this->update([
            'payment_type' => $sale->payment_type,

            'paid_amount'          => $sale->paid_amount,
            'paid_amount_afn'      => $sale->paid_amount_afn,
            'paid_amount_usd'      => $sale->paid_amount_usd,

            'remaining_amount'     => $sale->remaining_amount,
            'remaining_amount_afn' => $sale->remaining_amount_afn,
            'remaining_amount_usd' => $sale->remaining_amount_usd,
        ]);

        return $this;
    }
}