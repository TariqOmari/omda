<?php

namespace App\Http\Controllers;

use App\Models\FixedCapital;
use Illuminate\Http\Request;

class FixedCapitalController extends Controller
{
    /* INDEX — list + totals (AFN & USD) */
    public function index()
    {
        $items = FixedCapital::latest()->get();

        $totalAfn = 0;
        $totalUsd = 0;

        foreach ($items as $item) {
            $totalAfn += $item->amountInAfn();
            $totalUsd += $item->amountInUsd();
        }

        return response()->json([
            'items'  => $items,
            'totals' => [
                'afn' => round($totalAfn, 2),
                'usd' => round($totalUsd, 2),
            ],
        ]);
    }

    /* STORE */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'amount'        => 'required|numeric|min:0',
            'currency'      => 'required|in:AFN,USD',
            'exchange_rate' => 'required|numeric|min:0.0001',
            'entry_date'    => 'nullable|string|max:20', // 👈 Jalali string, same style as products
            'note'          => 'nullable|string|max:1000',
        ]);

        $item = FixedCapital::create($data);

        return response()->json($item, 201);
    }

    /* SHOW */
    public function show(FixedCapital $fixedCapital)
    {
        return response()->json($fixedCapital);
    }

    /* UPDATE */
    public function update(Request $request, FixedCapital $fixedCapital)
    {
        $data = $request->validate([
            'title'         => 'sometimes|required|string|max:255',
            'amount'        => 'sometimes|required|numeric|min:0',
            'currency'      => 'sometimes|required|in:AFN,USD',
            'exchange_rate' => 'sometimes|required|numeric|min:0.0001',
            'entry_date'    => 'sometimes|nullable|string|max:20',
            'note'          => 'sometimes|nullable|string|max:1000',
        ]);

        $fixedCapital->update($data);

        return response()->json($fixedCapital->fresh());
    }

    /* DESTROY */
    public function destroy(FixedCapital $fixedCapital)
    {
        $fixedCapital->delete();

        return response()->json(['message' => 'deleted']);
    }
}