<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Vpn;
use App\VpnSetting;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VpnController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['verifyCredentials']);
    }

    /**
     * Activate VPN for a user
     */
    // public function activateVpn(Request $request)
    // {
    //     $streaming_user_id = $request->user_id;

    //     // Get the streaming user from mysql2 connection
    //     $streamingUser = DB::connection('mysql2')->table('users')->where('id', $streaming_user_id)->first();

    //     if (!$streamingUser) {
    //         return response()->json(['error' => 'Streaming user not found'], 404);
    //     }
    //     $user_id = auth()->id();
    //     // Get the main user (who owns this streaming user) from the default connection
    //     $mainUser = User::find($user_id);

    //     if (!$mainUser) {
    //         return response()->json(['error' => 'Main user not found'], 404);
    //     }

    //     // Check if user has enough solde
    //     $currentSolde = (int)$mainUser->solde;
    //     if ($currentSolde < 1) {
    //         return response()->json(['error' => 'Insufficient balance. You need at least 1 point.'], 400);
    //     }

    //     // Check if this streaming user already has VPN activated
    //     $existingVpn = Vpn::where('username', $streamingUser->username)->first();
    //     if ($existingVpn) {
    //         return response()->json(['error' => 'VPN already activated for this user'], 400);
    //     }

    //     try {
    //         DB::beginTransaction();

    //         // Deduct 1 point from main user's solde
    //         $newSolde = $currentSolde - 1;
    //         $mainUser->update(['solde' => $newSolde]);

    //         // Determine which VPN host to assign (load balancing)
    //         $lastVpn = Vpn::latest()->first();
    //         $vpnHostId = 1; // Default to host 1

    //         if ($lastVpn && $lastVpn->vpn_host_id) {
    //             // Alternate between host 1 and 2
    //             $vpnHostId = $lastVpn->vpn_host_id == 1 ? 2 : 1;
    //         }

    //         // Create VPN entry with streaming user's credentials and assigned host
    //         $vpn = Vpn::create([
    //             'username' => $streamingUser->username,
    //             'password' => $streamingUser->password,
    //             'user_id' => $mainUser->id,
    //             'vpn_host_id' => $vpnHostId
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'VPN activated successfully! 1 point deducted from your balance.',
    //             'vpn' => $vpn,
    //             'new_balance' => $newSolde
    //         ]);

    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         return response()->json(['error' => 'Failed to activate VPN: ' . $e->getMessage()], 500);
    //     }
    // }
    public function activateVpn(Request $request)
{
    $streaming_user_id = $request->user_id;

    // Get the streaming user from mysql2 connection
    $streamingUser = DB::connection('mysql2')
        ->table('users')
        ->where('id', $streaming_user_id)
        ->first();

    if (!$streamingUser) {
        return response()->json(['error' => 'Streaming user not found'], 404);
    }

    $user_id = auth()->id();

    // Get the main user
    $mainUser = User::find($user_id);

    if (!$mainUser) {
        return response()->json(['error' => 'Main user not found'], 404);
    }

    // Check balance
    $currentSolde = (int) $mainUser->solde;
    if ($currentSolde < 1) {
        return response()->json([
            'error' => 'Insufficient balance. You need at least 1 point.'
        ], 400);
    }

    // Check if VPN already exists
    $existingVpn = Vpn::where('username', $streamingUser->username)->first();
    if ($existingVpn) {
        return response()->json([
            'error' => 'VPN already activated for this user'
        ], 400);
    }

    try {
        DB::beginTransaction();

        // Deduct 1 point
        $newSolde = $currentSolde - 1;
        $mainUser->update(['solde' => $newSolde]);

        // ✅ ALWAYS assign VPN host ID = 2
        $vpnHostId = 2;

        // Create VPN
        $vpn = Vpn::create([
            'username'    => $streamingUser->username,
            'password'    => $streamingUser->password,
            'user_id'     => $mainUser->id,
            'vpn_host_id' => $vpnHostId
        ]);

        DB::commit();

        return response()->json([
            'success'      => true,
            'message'      => 'VPN activated successfully! 1 point deducted from your balance.',
            'vpn'          => $vpn,
            'new_balance'  => $newSolde
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'error' => 'Failed to activate VPN: ' . $e->getMessage()
        ], 500);
    }
}


    /**
     * Get VPN settings for admin (returns both hosts)
     */
    public function getVpnSettings()
    {
        $host1 = VpnSetting::find(1);
        $host2 = VpnSetting::find(2);

        return response()->json([
            'host1' => $host1,
            'host2' => $host2
        ]);
    }

    /**
     * Update VPN settings (admin only) - Updates both hosts
     */
    public function updateVpnSettings(Request $request)
    {
        $user = Auth::user();
        if ($user->type !== 'Admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'host1' => 'required|string',
            'protocol1' => 'required|string|in:http,https',
            'port1' => 'required|string',
            'host2' => 'required|string',
            'protocol2' => 'required|string|in:http,https',
            'port2' => 'required|string'
        ]);

        // Update host 1
        VpnSetting::where('id', 1)->update([
            'host' => $request->host1,
            'protocol' => $request->protocol1,
            'port' => $request->port1
        ]);

        // Update host 2
        VpnSetting::where('id', 2)->update([
            'host' => $request->host2,
            'protocol' => $request->protocol2,
            'port' => $request->port2
        ]);

        return response()->json([
            'success' => true,
            'message' => 'VPN settings updated successfully'
        ]);
    }

    /**
     * Generate VPN download link
     */
    public function generateVpnDownload($streamingUserId)
    {
        // Get the streaming user from mysql2 connection
        $streamingUser = DB::connection('mysql2')->table('users')->where('id', $streamingUserId)->first();
        if (!$streamingUser) {
            return response()->json(['error' => 'Streaming user not found'], 404);
        }

        // Check if VPN is activated for this streaming user
        $vpn = Vpn::where('username', $streamingUser->username)->first();
        if (!$vpn) {
            return response()->json(['error' => 'VPN not activated for this user'], 404);
        }

        // Get the specific VPN host assigned to this user
        $vpnSetting = VpnSetting::find($vpn->vpn_host_id);
        if (!$vpnSetting) {
            return response()->json(['error' => 'VPN host not configured'], 404);
        }

        // Generate M3U URL with assigned VPN host
        $protocol = $vpnSetting->protocol ?? 'http';
        $host = $vpnSetting->host;
        $port = $vpnSetting->port;
        $username = $streamingUser->username;
        $password = $streamingUser->password;

        $m3uUrl = "{$protocol}://{$host}:{$port}/get.php?username={$username}&password={$password}&type=m3u_plus&output=ts";

        return response()->json($m3uUrl);
    }

    /**
     * Check if user has VPN activated
     */
    public function checkVpnStatus($streamingUserId)
    {
        // Get the streaming user from mysql2 connection
        $streamingUser = DB::connection('mysql2')->table('users')->where('id', $streamingUserId)->first();
        if (!$streamingUser) {
            return response()->json(['error' => 'Streaming user not found'], 404);
        }

        // Check if VPN is activated for this streaming user
        $vpn = Vpn::where('username', $streamingUser->username)->first();

        return response()->json([
            'has_vpn' => $vpn ? true : false,
            'vpn_data' => $vpn
        ]);
    }

    /**
     * Verify user credentials
     */
    public function verifyCredentials(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string'
        ]);

        // Check if username and password exist in streaming users database
        $user = DB::connection('mysql2')->table('users')
            ->where('username', $request->username)
            ->where('password', $request->password)
            ->first();

        return response()->json([
            'exists' => $user !== null
        ]);
    }
}
