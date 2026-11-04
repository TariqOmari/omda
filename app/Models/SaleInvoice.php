<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleInvoice extends Model
{
    protected $fillable = [
        'sale_ids', 
        'invoice_number',
        'sale_date', 
        'note',
        'status',
    ];

    protected $casts = [
        'sale_ids' => 'array',
    ];

    // Helper to fetch sales from the IDs
    public function sales()
    {
        return \App\Models\Sale::with('product', 'customer')
            ->whereIn('id', $this->sale_ids ?? [])
            ->get();
    }

    // ✅ FIX: Generate invoice number for THIS table
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ymd') . '-';
        
        // Look at the SaleInvoice table, not the Sale table
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
}