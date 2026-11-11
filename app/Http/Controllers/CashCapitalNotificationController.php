<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashCapitalNotification;

class CashCapitalNotificationController extends Controller
{
    // GET /api/cash-capitals/notifications
    public function index()
    {
        return response()->json([
            'items'        => CashCapitalNotification::orderByDesc('id')->limit(50)->get(),
            'unread_count' => CashCapitalNotification::where('is_read', false)->count(),
        ]);
    }

    // PUT /api/cash-capitals/notifications/{id}/read
    public function markAsRead($id)
    {
        $notification = CashCapitalNotification::findOrFail($id);
        $notification->update(['is_read' => true]);
        
        return response()->json(['success' => true]);
    }
}