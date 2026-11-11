<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedCapital extends Model
{
    protected $fillable = [
        'title',
        'amount',
        'currency',
        'exchange_rate',
        'entry_date',   // Jalali string (YYYY-MM-DD)
        'note',
    ];

    protected $casts = [
        'amount'        => 'float',
        'exchange_rate' => 'float',
    ];

    public function amountInAfn(): float
    {
        $rate = (float) ($this->exchange_rate ?: 1);
        return strtoupper($this->currency) === 'USD'
            ? $this->amount * $rate
            : $this->amount;
    }

    public function amountInUsd(): float
    {
        $rate = (float) ($this->exchange_rate ?: 1);
        return strtoupper($this->currency) === 'USD'
            ? $this->amount
            : ($rate > 0 ? $this->amount / $rate : 0);
    }
}