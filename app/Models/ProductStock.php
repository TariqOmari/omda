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

    /**
     * تنظیم موجودی بر اساس کوچکترین واحد
     */
    public function syncFromPieces($newPieces)
    {
        $newPieces = max(0, (int) $newPieces);
        $this->sub_sub_module_stock = $newPieces;

        $perCarton = $this->product ? $this->product->cansPerCarton() : 1;
        $perCan    = $this->product ? $this->product->piecesPerCan() : 1;

        if ($perCan > 1) {
            // اگر محصول دانه دارد (مثل بوتل که دانه دارد)
            $totalCans = intdiv($newPieces, $perCan);
            $this->sub_module_stock = $totalCans;
            $this->module_stock     = intdiv($totalCans, $perCarton);
        } elseif ($perCarton > 1) {
            // اگر محصول فقط بوتل دارد (دانه ندارد)
            $this->sub_module_stock = $newPieces;
            $this->module_stock     = intdiv($newPieces, $perCarton);
        } else {
            // اگر فقط کارتن است
            $this->module_stock = $newPieces;
            $this->sub_module_stock = 0;
        }

        return $this;
    }

    /**
     * 👈 تابع نجات‌بخش: دقیقاً می‌فهمد الان چند دانه/بوتل در انبار داریم
     * بدون اینکه با هم جمع بزند!
     */
    public function getCurrentPiecesCount()
    {
        $perCan = $this->product ? $this->product->piecesPerCan() : 1;
        
        // ۱. اگر دانه (sub_sub) داریم و محصول هم دانه دارد:
        if ($perCan > 1) {
            return (int) $this->sub_sub_module_stock;
        }
        
        // ۲. اگر دانه ندارد (perCan == 1) ولی بوتل دارد:
        if ($this->sub_module_stock > 0) {
            return (int) $this->sub_module_stock;
        }

        // ۳. اگر تازه ثبت شده و فقط کارتن دارد (بوتل صفر است):
        $perCarton = $this->product ? $this->product->cansPerCarton() : 1;
        return (int) $this->module_stock * $perCarton;
    }
}