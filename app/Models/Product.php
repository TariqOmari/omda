<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'supplier_id', 'name', 'category', 'barcode', 'photo',
        'module_name', 'sub_module_name', 'sub_module_qty',
        'sub_sub_module_name', 'sub_sub_module_qty',
        'price_mode', 'price', 'currency', 'exchange_rate',
        'final_price', 'final_qty',
        'sub_module_final_qty', 'sub_sub_module_final_qty',
        'final_price_afn', 'final_price_usd',
        'paid_amount', 'paid_amount_afn', 'paid_amount_usd',
        'remaining_amount', 'remaining_amount_afn', 'remaining_amount_usd',
        'payment_type',
        'production_date', 'expiry_date', 'stock_entry_date',
        'low_stock_warning', 'high_stock_warning',
         'sell_price_module', 'sell_price_sub_module', 'sell_price_sub_sub_module',
    ];

    protected $casts = [
        'sub_module_qty'              => 'integer',
        'sub_sub_module_qty'          => 'integer',
        'price'                       => 'float',
        'exchange_rate'               => 'float',
        'final_price'                 => 'float',
        'final_qty'                   => 'integer',
        'sub_module_final_qty'        => 'integer',
        'sub_sub_module_final_qty'    => 'integer',
        'final_price_afn'             => 'float',
        'final_price_usd'             => 'float',
        'paid_amount'                 => 'float',
        'paid_amount_afn'             => 'float',
        'paid_amount_usd'             => 'float',
        'remaining_amount'            => 'float',
        'remaining_amount_afn'        => 'float',
        'remaining_amount_usd'        => 'float',
        'low_stock_warning'           => 'integer',
        'high_stock_warning'          => 'integer',
            'sell_price_module' => 'float',
        'sell_price_sub_module' => 'float',
        'sell_price_sub_sub_module' => 'float',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    /* =========================================================
     | RELATIONSHIPS
     * ========================================================= */

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function restockRecords(): HasMany
    {
        return $this->hasMany(ProductRestockRecord::class)->latest();
    }

    /* =========================================================
     | STOCK HELPERS
     | final_qty                 = cartons (whole only)
     | sub_module_final_qty      = total sub-modules (bottles)
     | sub_sub_module_final_qty  = total pieces
     * ========================================================= */

    public function cansPerCarton(): int
    {
        return max(1, (int) ($this->sub_module_qty ?: 1));
    }

    public function piecesPerCan(): int
    {
        return max(1, (int) ($this->sub_sub_module_qty ?: 1));
    }

    public function piecesPerCarton(): int
    {
        return $this->cansPerCarton() * $this->piecesPerCan();
    }

    /**
     * Recalculate all 3 stock fields from a raw piece count.
     */
    public function setStockFromPieces(int $pieces): self
    {
        $pieces = max(0, $pieces);

        $perCarton = $this->cansPerCarton();
        $perCan    = $this->piecesPerCan();

        $totalCans = intdiv($pieces, $perCan);
        $remainder = $pieces % $perCan;

        $this->final_qty                 = intdiv($totalCans, $perCarton);
        $this->sub_module_final_qty      = $totalCans;
        $this->sub_sub_module_final_qty  = $totalCans * $perCan + $remainder;

        return $this;
    }

    public function totalPiecesInStock(): int
    {
        return (int) $this->sub_sub_module_final_qty;
    }

    public function addPiecesToStock(int $pieces): self
    {
        return $this->setStockFromPieces($this->totalPiecesInStock() + $pieces);
    }

    public function removePiecesFromStock(int $pieces): self
    {
        return $this->setStockFromPieces(max(0, $this->totalPiecesInStock() - $pieces));
    }

    /**
     * Total stock including base product + all restock records.
     * Base product counts as the original purchase; each restock adds on top.
     */
    public function getTotalStockAttribute(): array
    {
        $cartons = (int) $this->final_qty;
        $cans    = (int) $this->sub_module_final_qty;
        $pieces  = (int) $this->sub_sub_module_final_qty;

        // If relation already loaded, use it (avoids extra query).
        $records = $this->relationLoaded('restockRecords')
            ? $this->restockRecords
            : $this->restockRecords()->get();

        foreach ($records as $r) {
            $cartons += (int) $r->add_module_qty;
            $cans    += (int) $r->add_sub_module_qty;
            $pieces  += (int) $r->add_sub_sub_module_qty;
        }

        return [
            'cartons' => $cartons,
            'cans'    => $cans,
            'pieces'  => $pieces,
        ];
    }

    /* =========================================================
     | PRICE HELPERS
     * ========================================================= */

    public function calculatePrice(int $qty): array
    {
        $cansPerCarton = $this->cansPerCarton();
        $piecesPerCan  = $this->piecesPerCan();

        $totalCartons = $qty;
        $totalCans    = $qty * $cansPerCarton;
        $totalPieces  = $totalCans * $piecesPerCan;

        switch ($this->price_mode) {
            case 'sub_sub_module': $billable = $totalPieces;  break;
            case 'sub_module':     $billable = $totalCans;    break;
            default:               $billable = $totalCartons;
        }

        return [
            'qty'            => $qty,
            'price_mode'     => $this->price_mode,
            'price'          => (float) $this->price,
            'total_cartons'  => $totalCartons,
            'total_cans'     => $totalCans,
            'total_pieces'   => $totalPieces,
            'billable_units' => $billable,
            'final_price'    => round($billable * $this->price, 2),
        ];
    }

    public static function convertCurrency(float $amount, string $fromCurrency, float $rate): array
    {
        $rate = max(0.0001, $rate);

        if (strtoupper($fromCurrency) === 'USD') {
            return [
                'afn' => round($amount * $rate, 2),
                'usd' => round($amount, 2),
            ];
        }

        return [
            'afn' => round($amount, 2),
            'usd' => round($amount / $rate, 2),
        ];
    }

    public function applyFinalPrice(int $qty, array $payment = []): self
    {
        $calc = $this->calculatePrice($qty);

        // price
        $this->final_price = $calc['final_price'];

        // stock — sets all 3 fields correctly
        $this->setStockFromPieces($calc['total_pieces']);

        // currency conversion
        $rate = (float) ($this->exchange_rate ?: 1);
        $curr = strtoupper($this->currency ?: 'AFN');

        $converted = self::convertCurrency($this->final_price, $curr, $rate);
        $this->final_price_afn = $converted['afn'];
        $this->final_price_usd = $converted['usd'];

        // payment
        $type = $payment['payment_type'] ?? $this->payment_type ?? 'cash';

        switch ($type) {
            case 'loan_full':
                $this->paid_amount      = 0;
                $this->remaining_amount = $this->final_price;
                break;

            case 'loan_partial':
                $paid = (float) ($payment['paid_amount'] ?? $this->paid_amount ?? 0);
                $paid = max(0, min($paid, $this->final_price));
                $this->paid_amount      = $paid;
                $this->remaining_amount = round($this->final_price - $paid, 2);
                break;

            case 'cash':
            default:
                $this->paid_amount      = $this->final_price;
                $this->remaining_amount = 0;
        }

        $this->payment_type = $type;

        $paidConv      = self::convertCurrency($this->paid_amount, $curr, $rate);
        $remainingConv = self::convertCurrency($this->remaining_amount, $curr, $rate);

        $this->paid_amount_afn      = $paidConv['afn'];
        $this->paid_amount_usd      = $paidConv['usd'];
        $this->remaining_amount_afn = $remainingConv['afn'];
        $this->remaining_amount_usd = $remainingConv['usd'];

        $this->save();
        return $this;
    }


        public function currentStock()
    {
        return $this->hasOne(ProductStock::class);
    }



}