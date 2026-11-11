<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashCapitalTransaction;
use App\Models\CashCapitalNotification;
use Illuminate\Support\Facades\DB;

class CashCapitalController extends Controller
{
    public function index()
    {
        $afnBalance = CashCapitalTransaction::where('currency', 'AFN')->sum('amount');
        $usdBalance = CashCapitalTransaction::where('currency', 'USD')->sum('amount');

        return response()->json([
            'accounts' => [
                [
                    'currency'    => 'AFN',
                    'balance'     => (float) $afnBalance,
                    'balance_afn' => (float) $afnBalance,
                    'balance_usd' => 0,
                ],
                [
                    'currency'    => 'USD',
                    'balance'     => (float) $usdBalance,
                    'balance_afn' => 0,
                    'balance_usd' => (float) $usdBalance,
                ],
            ],
            'totals' => [
                'afn' => (float) $afnBalance,
                'usd' => (float) $usdBalance,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount'       => 'required|numeric|min:0.01',
            'currency'     => 'required|in:AFN,USD',
            'action'       => 'required|in:add,withdraw',
            'persian_date' => 'required|string|max:10',
            'note'         => 'nullable|string|max:255',
        ]);

        $signedAmount = $data['action'] === 'withdraw' 
            ? -1 * (float) $data['amount'] 
            : (float) $data['amount'];

        if ($data['action'] === 'withdraw') {
            $currentBalance = CashCapitalTransaction::where('currency', $data['currency'])->sum('amount');
            if ($data['amount'] > $currentBalance) {
                return response()->json([
                    'message' => "موجودی {$data['currency']} کافی نیست. موجودی فعلی: {$currentBalance}",
                ], 422);
            }
        }

        $result = DB::transaction(function () use ($data, $signedAmount) {
            $tx = CashCapitalTransaction::create([
                'amount'       => $signedAmount,
                'currency'     => $data['currency'],
                'persian_date' => $data['persian_date'],
                'note'         => $data['note'] ?? null,
            ]);

            $label = $data['action'] === 'add' ? 'افزودن' : 'برداشت';
            $notif = CashCapitalNotification::create([
                'cash_capital_transaction_id' => $tx->id,
                'currency'     => $data['currency'],
                'action'       => $data['action'],
                'amount'       => $data['amount'],
                'persian_date' => $data['persian_date'],
                'message'      => "{$label} سرمایه نقدی: " . number_format($data['amount'], 2) . " {$data['currency']}",
                'is_read'      => false,
            ]);

            return [$tx, $notif];
        });

        return response()->json([
            'item'         => $result[0],
            'notification' => $result[1],
        ], 201);
    }

    public function transactions(Request $request)
    {
        $currency = $request->query('currency');
        $query = CashCapitalTransaction::orderByDesc('id');

        if (in_array($currency, ['AFN', 'USD'])) {
            $query->where('currency', $currency);
        }

        return response()->json(['items' => $query->get()]);
    }

    public function notifications()
    {
        return response()->json([
            'items'        => CashCapitalNotification::orderByDesc('id')->limit(50)->get(),
            'unread_count' => CashCapitalNotification::where('is_read', false)->count(),
        ]);
    }

    public function markNotificationRead($id)
    {
        $notif = CashCapitalNotification::findOrFail($id);
        $notif->update(['is_read' => true]);
        
        return response()->json(['success' => true]);
    }
}