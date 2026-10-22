<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number',
        'customer_id', 'customer_name',
        'product_id', 'product_name',

        'sale_unit', 'sale_unit_name', 'qty_sold',
        'cartons_used', 'sub_units_used', 'pieces_used',

        'module_name', 'sub_module_name', 'sub_module_qty',
        'sub_sub_module_name', 'sub_sub_module_qty',

        'unit_price', 'currency', 'exchange_rate',
        'total_price', 'total_price_afn', 'total_price_usd','sale_date',

        'payment_type',
        'paid_amount', 'paid_amount_afn', 'paid_amount_usd',
        'remaining_amount', 'remaining_amount_afn', 'remaining_amount_usd',

        'cost_price', 'profit',
        'note',
    ];

    protected $casts = [
        'qty_sold'               => 'integer',
        'cartons_used'           => 'integer',
        'sub_units_used'         => 'integer',
        'pieces_used'            => 'integer',
        'sub_module_qty'         => 'integer',
        'sub_sub_module_qty'     => 'integer',

        'unit_price'             => 'float',
        'exchange_rate'          => 'float',
        'total_price'            => 'float',
        'total_price_afn'        => 'float',
        'total_price_usd'        => 'float',

        'paid_amount'            => 'float',
        'paid_amount_afn'        => 'float',
        'paid_amount_usd'        => 'float',
        'remaining_amount'       => 'float',
        'remaining_amount_afn'   => 'float',
        'remaining_amount_usd'   => 'float',

        'cost_price'             => 'float',
        'profit'                 => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ymd') . '-';
        $last = self::where('invoice_number', 'like', $prefix . '%')
                    ->orderByDesc('id')
                    ->value('invoice_number');

        $next = 1;
        if ($last) {
            $parts = explode('-', $last);
            $next = ((int) end($parts)) + 1;
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function applyPayment(float $amount): self
    {
        if ($this->remaining_amount <= 0) {
            return $this;
        }

        $newPaid = min($this->paid_amount + $amount, $this->total_price);
        $this->paid_amount      = round($newPaid, 2);
        $this->remaining_amount = round($this->total_price - $newPaid, 2);

        $rate = (float) ($this->exchange_rate ?: 1);
        $curr = strtoupper($this->currency ?: 'AFN');

        $paidConv      = Product::convertCurrency($this->paid_amount, $curr, $rate);
        $remainingConv = Product::convertCurrency($this->remaining_amount, $curr, $rate);

        $this->paid_amount_afn      = $paidConv['afn'];
        $this->paid_amount_usd      = $paidConv['usd'];
        $this->remaining_amount_afn = $remainingConv['afn'];
        $this->remaining_amount_usd = $remainingConv['usd'];

        $this->payment_type = $this->remaining_amount <= 0 ? 'cash' : 'loan_partial';

        $this->save();
        return $this;
    }

    public static function breakdownFromPieces(int $pieces, int $perCarton, int $perCan): array
    {
        $perCarton = max(1, $perCarton);
        $perCan    = max(1, $perCan);

        $cartons = intdiv($pieces, $perCarton * $perCan);
        $rem     = $pieces % ($perCarton * $perCan);
        $cans    = intdiv($rem, $perCan);
        $remain  = $rem % $perCan;

        return [
            'cartons' => $cartons,
            'cans'    => $cans,
            'pieces'  => $remain,
        ];
    }
}