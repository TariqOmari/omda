<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    protected $fillable = [
        'return_number', 'sale_id',
        'invoice_number', 'product_id', 'product_name',
        'customer_id', 'customer_name',
        'return_unit', 'return_unit_name', 'qty_returned',
        'pieces_returned', 'cartons_returned', 'sub_units_returned',
        'unit_price', 'total_price', 'cost_price', 'profit_reversed',
        'currency', 'exchange_rate',
        'total_price_afn', 'total_price_usd',
        'cost_price_afn', 'cost_price_usd',
        'refund_type', 'refund_amount',
        'refund_amount_afn', 'refund_amount_usd',
        'debt_reduced',
        'return_date', 'note',
    ];

    protected $casts = [
        'qty_returned'       => 'integer',
        'pieces_returned'    => 'integer',
        'cartons_returned'   => 'integer',
        'sub_units_returned' => 'integer',
        'unit_price'         => 'float',
        'total_price'        => 'float',
        'cost_price'         => 'float',
        'profit_reversed'    => 'float',
        'exchange_rate'      => 'float',
        'total_price_afn'    => 'float',
        'total_price_usd'    => 'float',
        'cost_price_afn'     => 'float',
        'cost_price_usd'     => 'float',
        'refund_amount'      => 'float',
        'refund_amount_afn'  => 'float',
        'refund_amount_usd'  => 'float',
        'debt_reduced'       => 'float',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public static function generateReturnNumber(): string
    {
        $prefix = 'RET-' . now()->format('Ymd') . '-';
        $last = self::where('return_number', 'like', $prefix . '%')
                    ->orderByDesc('id')
                    ->value('return_number');

        $next = 1;
        if ($last) {
            $parts = explode('-', $last);
            $next = ((int) end($parts)) + 1;
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}