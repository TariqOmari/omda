<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashCapitalTransaction extends Model
{
    protected $table = 'cash_capital_transactions';

    protected $fillable = [
        'amount',
        'currency',
        'persian_date',
        'note',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function notification()
    {
        return $this->hasOne(CashCapitalNotification::class, 'cash_capital_transaction_id');
    }
}