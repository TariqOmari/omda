<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashCapitalNotification extends Model
{
    protected $table = 'cash_capital_notifications';

    protected $fillable = [
        'cash_capital_transaction_id',
        'currency',
        'action',
        'amount',
        'persian_date',
        'message',
        'is_read',
    ];

    protected $casts = [
        'amount'  => 'float',
        'is_read' => 'boolean',
    ];

    public function transaction()
    {
        return $this->belongsTo(CashCapitalTransaction::class, 'cash_capital_transaction_id');
    }
}