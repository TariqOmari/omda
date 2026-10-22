<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // اول مقادیر اعشاری رو به قطعه تبدیل کن
        DB::table('products')->get()->each(function ($p) {
            $perCarton = max(1, (int) ($p->sub_module_qty ?: 1));
            $perCan    = max(1, (int) ($p->sub_sub_module_qty ?: 1));

            $cartons = (float) $p->final_qty;
            $pieces  = (int) round($cartons * $perCarton * $perCan);

            DB::table('products')
                ->where('id', $p->id)
                ->update(['final_qty' => $pieces]);
        });

        // حالا نوع ستون رو به integer برگردون
        DB::statement('ALTER TABLE products MODIFY final_qty INT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products MODIFY final_qty DECIMAL(12,3) NOT NULL DEFAULT 0');
    }
};