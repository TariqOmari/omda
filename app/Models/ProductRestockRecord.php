<?php
// app/Models/ProductRestockRecord.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRestockRecord extends Model
{
    protected $fillable = [
        'product_id',
        'add_module_qty', 'add_sub_module_qty', 'add_sub_sub_module_qty',
        'module_name', 'sub_module_name', 'sub_sub_module_name',
        'sub_module_qty', 'sub_sub_module_qty', 'price_mode',
        'price', 'currency', 'exchange_rate',
        'total_price', 'total_price_afn', 'total_price_usd',
        'payment_type', 'paid_amount', 'remaining_amount',
        'paid_amount_afn', 'paid_amount_usd',
        'remaining_amount_afn', 'remaining_amount_usd',
        'stock_entry_date', 'production_date', 'expiry_date', 'note',
    ];

    protected $casts = [
        'product_id'              => 'integer',
        'add_module_qty'          => 'integer',
        'add_sub_module_qty'      => 'integer',
        'add_sub_sub_module_qty'  => 'integer',
        'sub_module_qty'          => 'integer',
        'sub_sub_module_qty'      => 'integer',
        'price'                   => 'float',
        'exchange_rate'           => 'float',
        'total_price'             => 'float',
        'total_price_afn'         => 'float',
        'total_price_usd'         => 'float',
        'paid_amount'             => 'float',
        'remaining_amount'        => 'float',
        'paid_amount_afn'         => 'float',
        'paid_amount_usd'         => 'float',
        'remaining_amount_afn'    => 'float',
        'remaining_amount_usd'    => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Compute totals for this batch using product helper math. */
    public function computeTotals(): void
    {
        $qty = max(1, (int) $this->add_module_qty);
        $cansPerCarton = max(1, (int) $this->sub_module_qty);
        $piecesPerCan  = max(1, (int) $this->sub_sub_module_qty);

        $billable = match ($this->price_mode) {
            'sub_sub_module' => $qty * $cansPerCarton * $piecesPerCan,
            'sub_module'     => $qty * $cansPerCarton,
            default          => $qty,
        };

        $total = round($billable * (float) $this->price, 2);

        $rate = (float) ($this->exchange_rate ?: 1);
        $curr = strtoupper($this->currency ?: 'AFN');

        $conv = Product::convertCurrency($total, $curr, $rate);
        $this->total_price     = $total;
        $this->total_price_afn = $conv['afn'];
        $this->total_price_usd = $conv['usd'];

        $paid = match ($this->payment_type) {
            'cash'        => $total,
            'loan_full'   => 0,
            default       => min((float) $this->paid_amount, $total),
        };
        $remaining = max(0, $total - $paid);

        $paidConv = Product::convertCurrency($paid, $curr, $rate);
        $remConv  = Product::convertCurrency($remaining, $curr, $rate);

        $this->paid_amount            = round($paid, 2);
        $this->remaining_amount       = round($remaining, 2);
        $this->paid_amount_afn        = $paidConv['afn'];
        $this->paid_amount_usd        = $paidConv['usd'];
        $this->remaining_amount_afn   = $remConv['afn'];
        $this->remaining_amount_usd   = $remConv['usd'];
    }
}