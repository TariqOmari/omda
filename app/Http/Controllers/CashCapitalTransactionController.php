<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashCapitalTransaction;
use App\Models\CashCapitalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashCapitalTransactionController extends Controller
{
    // GET /api/cash-capitals
    public function index()
    {
        // 1. Calculate AFN balance (Sum of all + and - amounts for AFN)
        $afnBalance = CashCapitalTransaction::where('currency', 'AFN')->sum('amount');
        
        // 2. Calculate USD balance (Sum of all + and - amounts for USD)
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

    // POST /api/cash-capitals
    public function store(Request $request)
    {
        $data = $request->validate([
            'amount'       => 'required|numeric|min:0.01',
            'currency'     => 'required|in:AFN,USD',
            'action'       => 'required|in:add,withdraw',
            'persian_date' => 'required|string|max:10',
            'note'         => 'nullable|string|max:255',
        ]);

        // Make the amount negative if it's a withdrawal
        $signedAmount = $data['action'] === 'withdraw' 
            ? -1 * (float) $data['amount'] 
            : (float) $data['amount'];

        // Check if they have enough money to withdraw
        if ($data['action'] === 'withdraw') {
            $currentBalance = CashCapitalTransaction::where('currency', $data['currency'])->sum('amount');
            if ($data['amount'] > $currentBalance) {
                return response()->json([
                    'message' => "موجودی {$data['currency']} کافی نیست. موجودی فعلی: {$currentBalance}",
                ], 422);
            }
        }

        // Save to Database
        $result = DB::transaction(function () use ($data, $signedAmount) {
            
            // 1. Create Transaction
            $tx = CashCapitalTransaction::create([
                'amount'       => $signedAmount,
                'currency'     => $data['currency'],
                'persian_date' => $data['persian_date'],
                'note'         => $data['note'] ?? null,
            ]);

            // 2. Create Notification
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

    // GET /api/cash-capitals/transactions
    public function history(Request $request)
    {
        $currency = $request->query('currency');

        $query = CashCapitalTransaction::orderByDesc('id');

        if (in_array($currency, ['AFN', 'USD'])) {
            $query->where('currency', $currency);
        }

        return response()->json(['items' => $query->get()]);
    }
}