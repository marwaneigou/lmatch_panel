<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicApiController extends Controller
{
    /**
     * Verify user credentials (Public API - No Authentication Required)
     */
    public function verifyCredentials(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string'
        ]);

        // Check if username and password exist in VPN table (users with activated VPN)
        $vpnUser = DB::table('vpns')
            ->where('username', $request->username)
            ->where('password', $request->password)
            ->first();

        return response()->json([
            'exists' => $vpnUser !== null
        ]);
    }
}
