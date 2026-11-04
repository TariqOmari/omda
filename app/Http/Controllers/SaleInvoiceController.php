<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleInvoice;
use Illuminate\Http\Request;

class SaleInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = SaleInvoice::latest()->get();

        return $invoices->map(function ($invoice) {
            $arr = $invoice->toArray();
            
            // Attach the actual sales from the array of IDs
            $arr['sales'] = Sale::with('product', 'customer')
                ->whereIn('id', $invoice->sale_ids ?? [])
                ->get();
                
            return $arr;
        });
    }

    public function show(SaleInvoice $saleInvoice)
    {
        $arr = $saleInvoice->toArray();
        
        $arr['sales'] = Sale::with('product', 'customer')
            ->whereIn('id', $saleInvoice->sale_ids ?? [])
            ->get();

        return response()->json($arr);
    }

    public function storeMulti(Request $request)
    {
        $data = $request->validate([
            'sale_ids'   => 'required|array|min:1',
            'sale_ids.*' => 'integer|exists:sales,id',
            'sale_date'  => 'nullable|string|max:20',
            'note'       => 'nullable|string',
        ]);

        $invoice = SaleInvoice::create([
            // ✅ FIX: Use SaleInvoice's own generator
            'invoice_number' => SaleInvoice::generateInvoiceNumber(), 
            'sale_ids'       => $data['sale_ids'],
            'sale_date'      => $data['sale_date'] ?? now()->toDateString(),
            'note'           => $data['note'] ?? null,
            'status'         => 'active',
        ]);

        return response()->json($invoice->fresh(), 201);
    }

    public function destroy(SaleInvoice $saleInvoice)
    {
        $saleInvoice->delete();
        return response()->json(['message' => 'Sale invoice deleted']);
    }
}