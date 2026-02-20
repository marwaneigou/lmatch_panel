<?php

namespace App\Http\Controllers;

use App\TransactionLog;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        // Ensure only Admin can access this (or add logic for resellers if needed later)
        // The task specifies "Admin Transaction Logs Page"
        if (Auth::user()->type !== 'Admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = TransactionLog::with('reseller:id,name');

        if ($request->has('reseller_id') && $request->reseller_id != 'all' && $request->reseller_id != '') {
            $query->where('reseller_id', $request->reseller_id);
        }

        if ($request->has('action') && $request->action != 'all' && $request->action != '') {
            $query->where('action', $request->action);
        }

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('details', 'LIKE', "%{$search}%")
                    ->orWhere('target_id', 'LIKE', "%{$search}%")
                    ->orWhere('target_type', 'LIKE', "%{$search}%");
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($logs);
    }
}
