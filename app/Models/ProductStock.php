<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $fillable = [
        'product_id', 
        'module_stock', 
        'sub_module_stock', 
        'sub_sub_module_stock'
    ];

    protected $casts = [
        'module_stock'         => 'integer',
        'sub_module_stock'     => 'integer',
        'sub_sub_module_stock' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}