<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPurchaseBill;
use Illuminate\Http\Request;

class ProductPurchaseBillController extends Controller
{
    public function index(Request $request)
    {
        $bills = ProductPurchaseBill::latest()->get();

        return $bills->map(function ($bill) {
            $arr = $bill->toArray();
            
            // Attach products from product_ids
            $arr['products'] = Product::with('supplier')
                ->whereIn('id', $bill->product_ids ?? [])
                ->get();
                
            $firstProduct = $arr['products']->first();
            $arr['supplier_name'] = $firstProduct?->supplier?->supplier_name;

            return $arr;
        });
    }

    public function show(ProductPurchaseBill $bill)
    {
        $arr = $bill->toArray();
        
        // THIS IS THE MISSING PART: Attach the products!
        $arr['products'] = Product::with('supplier')
            ->whereIn('id', $bill->product_ids ?? [])
            ->get();
            
        $firstProduct = $arr['products']->first();
        $arr['supplier_name'] = $firstProduct?->supplier?->supplier_name;

        return response()->json($arr);
    }

    public function destroy(ProductPurchaseBill $bill)
    {
        $bill->delete();
        return response()->json(['message' => 'Bill deleted']);
    }
}