<?php

namespace App\Http\Controllers;

use App\MultiCode;
use App\ActiveCode;
use App\User;
use App\ResellerStatistic;
use App\SubResiler;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use DB;
use Illuminate\Validation\Rule;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class MultiCodeController extends Controller
{


    public function pdf(Request $request, $codes, $duree, $coupons)
    {
        $codes = explode(",", $codes);
        $coupons = explode(",", $coupons);
        $date = date("d/m/Y");

        $pdf = \PDF::loadView('masscodes', ['codes' => $codes, 'date' => $date, 'duree' => $duree, 'coupons' => $coupons]);

        $pdf->save(storage_path() . '_filename.pdf');

        return $pdf->download('MassCodes.pdf');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $query = request('query');

        // Optimize: Single line sub-reseller lookup
        $subArray = SubResiler::where('res_id', $user_id)->pluck('user_id')->toArray();
        $subArray[] = $user_id;

        // Build the main query
        if ($user_type !== 'Admin') {
            $MultiCode = MultiCode::join('users', 'multi_codes.user_id', '=', 'users.id')
                ->select('multi_codes.*', 'users.name AS UserName')
                ->whereIn('multi_codes.user_id', $subArray)
                ->whereNull('multi_codes.deleted_at');

            if ($query) {
                $MultiCode = $MultiCode->where(function ($q) use ($query) {
                    $q->where('multi_codes.number', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });
            }
        } else {
            $MultiCode = MultiCode::join('users', 'multi_codes.user_id', '=', 'users.id')
                ->select('multi_codes.*', 'users.name AS UserName')
                ->whereNull('multi_codes.deleted_at');

            // FIX: Proper grouping for orWhere (was breaking deleted_at condition)
            if ($query) {
                $MultiCode = $MultiCode->where(function ($q) use ($query) {
                    $q->where('multi_codes.number', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });
            }
        }

        $MultiCode = $MultiCode->orderBy('multi_codes.id', 'desc')->paginate(20);

        // Early return if no results
        if ($MultiCode->isEmpty()) {
            return response()->json($MultiCode);
        }

        // Collect all numbers and package IDs for batch queries
        $numbers = $MultiCode->pluck('number')->toArray();
        $packageIds = $MultiCode->pluck('package_id')->unique()->toArray();

        // BATCH 1: Get users from mysql2 (remote)
        $remoteUsers = DB::connection('mysql2')->table('users')
            ->select('username', 'is_trial', 'exp_date', 'macadress', 'id', 'bouquet')
            ->whereIn('username', $numbers)
            ->get()
            ->keyBy('username');

        // BATCH 2: Get users_activecode (for bouquets fallback)
        $activeCodes = DB::connection('mysql2')->table('users_activecode')
            ->select('username', 'bouquet')
            ->whereIn('username', $numbers)
            ->get()
            ->keyBy('username');

        // Get all remote user IDs for activity queries
        $remoteUserIds = $remoteUsers->pluck('id')->toArray();

        // BATCH 3: Get online activities
        $onlineActivities = collect();
        $onlineUserIds = [];
        if (!empty($remoteUserIds)) {
            $onlineActivities = DB::connection('mysql2')->table('con_activities')
                ->select('date_start', 'geoip_country_code', 'user_ip', 'stream_id', 'divergence', 'user_id')
                ->whereIn('user_id', $remoteUserIds)
                ->orderBy('activity_id', 'desc')
                ->get()
                ->groupBy('user_id');
            $onlineUserIds = $onlineActivities->keys()->toArray();
        }

        // BATCH 4: Get log activities for offline users
        $logActivities = collect();
        if (!empty($remoteUserIds)) {
            $offlineUserIds = array_diff($remoteUserIds, $onlineUserIds);
            if (!empty($offlineUserIds)) {
                $logActivities = DB::connection('mysql2')->table('log_con_activities')
                    ->select('date_start', 'geoip_country_code', 'user_ip', 'stream_id', 'divergence', 'user_id')
                    ->whereIn('user_id', $offlineUserIds)
                    ->get()
                    ->groupBy('user_id');
            }
        }
        $allActivities = $onlineActivities->merge($logActivities);

        // BATCH 5: Get stream names
        $streamIds = $allActivities->flatten()->pluck('stream_id')->filter()->unique()->toArray();
        $streams = collect();
        if (!empty($streamIds)) {
            $streams = DB::connection('mysql2')->table('streams')
                ->select('id', 'stream_display_name')
                ->whereIn('id', $streamIds)
                ->get()
                ->keyBy('id');
        }

        // BATCH 6: Get packages for fallback
        $packages = collect();
        if (!empty($packageIds)) {
            $packages = DB::connection('mysql2')->table('packages')
                ->select('id', 'is_trial')
                ->whereIn('id', $packageIds)
                ->get()
                ->keyBy('id');
        }

        $now = now();

        // Process each code
        foreach ($MultiCode as $active) {
            // Defaults
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $active->has_is_trial = false;
            $active->pack = $active->pack ? json_decode($active->pack) : '';

            // Get bouquets from remote user or activecode
            $remoteUser = $remoteUsers->get($active->number);
            $activeCode = $activeCodes->get($active->number);

            if ($remoteUser && $remoteUser->bouquet) {
                $active->selected_bouquets = json_decode($remoteUser->bouquet);
            } elseif ($activeCode && $activeCode->bouquet) {
                $active->selected_bouquets = json_decode($activeCode->bouquet);
            }

            if ($remoteUser) {
                $active->is_trial = $remoteUser->is_trial;
                $active->has_is_trial = true;

                // MAC address fallback
                if (empty($active->mac) && !empty($remoteUser->macadress)) {
                    $active->mac = str_replace(":", "", $remoteUser->macadress);
                }

                // Expiration date
                if (!empty($remoteUser->exp_date)) {
                    $active->time = date("Y-m-d H:i:s", $remoteUser->exp_date);
                    $expDate = Carbon::createFromTimestamp($remoteUser->exp_date);
                    $active->days = $expDate->isFuture() ? $now->diffInDays($expDate) . " days" : "0 days";
                }

                // Online status
                $active->online = in_array($remoteUser->id, $onlineUserIds) ? 1 : 0;

                // Activity data
                $userActivityGroup = $allActivities->get($remoteUser->id);
                if ($userActivityGroup && $userActivityGroup->isNotEmpty()) {
                    $activity = $userActivityGroup->first();
                    $active->last_connection = date("Y-m-d", $activity->date_start);
                    $active->flag = $activity->geoip_country_code;
                    $active->user_ip = $activity->user_ip;
                    $active->stream_id = $activity->stream_id;

                    if ($active->online == 1) {
                        $active->latency = (100 - $activity->divergence) / 20;
                    }

                    $activityDate = Carbon::createFromTimestamp($activity->date_start);
                    $active->last_seen_date = $now->diff($activityDate)->format('%hh %im %ss');

                    // Stream name
                    if ($activity->stream_id && $streams->has($activity->stream_id)) {
                        $active->stream_name = $streams->get($activity->stream_id)->stream_display_name;
                    }
                }
            } else {
                // Fallback to package for is_trial
                if ($packages->has($active->package_id)) {
                    $active->is_trial = $packages->get($active->package_id)->is_trial;
                }
            }
        }

        return response()->json($MultiCode);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */


    public function check(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response(['message' => 'Unauthorised'], 403);
        }

        $user_type = $user->type;
        $user_solde = $user->solde;

        $max = ($user_type != 'Admin' && $request->max > $user_solde)
            ? $user_solde
            : $request->max;

        $start = ($request->pack == 185) ? "185001" : $request->NumStart;
        $len = ($request->pack == 185) ? $request->len - 6 : $request->len - 3;

        $existingCodes = array_merge(
            MultiCode::where('number', 'like', $start . '%')->pluck('number')->toArray(),
            ActiveCode::where('number', 'like', $start . '%')->pluck('number')->toArray(),
            DB::connection('mysql2')->table('users')->where('username', 'like', $start . '%')->pluck('username')->toArray()
        );
        $existingCodes = array_flip($existingCodes);

        $array = [];
        while (count($array) < $max) {
            $random_number = str_shuffle((string) mt_rand(0, PHP_INT_MAX));
            $randome_code = substr($random_number, 0, $len);
            $rand = $start . $randome_code;

            if (!isset($existingCodes[$rand]) && !in_array($rand, $array)) {
                $array[] = $rand;
                $existingCodes[$rand] = true; // Mark as used
            }
        }

        sort($array);
        return response()->json(array_values($array));
    }

    public function SendMassCode()
    {
        request()->validate(
            [
                'name' => 'required',
                'len' => 'required',
                'pack' => 'required',
                'hh' => 'required',
                'myArray' => 'max:2000',
            ],
            [
                'hh.required' => 'please Random Active Codes',
                'myArray.max' => 'Max Active Code is 2000',
                'name.required' => 'please put your name',
                'len.required' => 'please choose length of code',
                'pack.required' => 'please Choose Package',
            ]
        );
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:multi_codes',
            'len' => 'required',
            'pack' => 'required',
            'hh' => 'required',
        ], [
            'hh.required' => 'Please provide random active codes.',
            'name.required' => 'Please provide a name.',
            'len.required' => 'Please specify the length of the code.',
            'pack.required' => 'Please choose a package.',
        ]);

        DB::beginTransaction();

        try {
            $user = Auth::user();
            $user_id = $user->id;
            $user_type = $user->type;
            $user_solde = $user->solde;
            $user_test_solde = $user->solde_test;

            // Direct package lookup (without caching)
            $mypackage = DB::connection('mysql2')->table('packages')->where('id', $request['pack'])->first();

            // More efficient approach: Check codes in batches
            $array1 = array_unique(array_filter(array_map('trim', explode("\n", $request->hh))));

            // if (count($array1) > 1000) {
            //     return response()->json(['error' => 'Max active code is 1000.'], 422);
            // }

            // Check for existing codes in batches
            $validCodes = [];
            $chunkSize = 100;

            foreach (array_chunk($array1, $chunkSize) as $codeChunk) {
                // Check against MultiCodes
                $existingMulti = MultiCode::whereIn('number', $codeChunk)->pluck('number')->toArray();

                // Check against ActiveCodes
                $existingActive = ActiveCode::whereIn('number', $codeChunk)->pluck('number')->toArray();

                // Check against usernames
                $existingUsers = DB::connection('mysql2')->table('users')
                    ->whereIn('username', $codeChunk)
                    ->pluck('username')
                    ->toArray();

                $existingInChunk = array_merge($existingMulti, $existingActive, $existingUsers);

                // Add non-existing codes to valid list
                foreach ($codeChunk as $code) {
                    if (!in_array($code, $existingInChunk)) {
                        $validCodes[] = $code;
                    }
                }
            }


            $pack_list = json_decode($mypackage->bouquets, true);
            $request_bouquets = json_decode($request->bouquets, true);
            $request->bouquets = json_encode(array_values(array_intersect((array) $request_bouquets, (array) $pack_list)));

            // Pre-generate all passwords at once
            $passwords = $this->generatePasswords(count($validCodes));

            // Duration calculations
            $durationMapping = [
                '1_years' => 365,
                '1_months' => 30,
                '3_months' => 90,
                '6_months' => 180,
                '24_hours' => 1,
                '10_days' => 10,
                '2_months' => 60,
                '366_days' => 366,
            ];

            $duration_p = $mypackage->is_trial ? $mypackage->trial_duration : $mypackage->official_duration;
            $duration_in = $mypackage->is_trial ? $mypackage->trial_duration_in : $mypackage->official_duration_in;
            $duration_key = "{$duration_p}_{$duration_in}";
            $duration = $durationMapping[$duration_key] ?? $duration_p;

            // Calculate cost multiplier
            switch ($duration) {
                case 30:
                    $costMultiplier = 0.1;
                    break;
                case 90:
                    $costMultiplier = 0.3;
                    break;
                case 180:
                    $costMultiplier = 0.6;
                    break;
                default:
                    $costMultiplier = 1;
                    break;
            }

            // Check balance upfront
            $validCodesCount = count($validCodes);

            if ($user_type != 'Admin') {
                $required_solde = $validCodesCount * $costMultiplier;
                $user_solde_available = $mypackage->is_trial ? $user_test_solde : $user_solde;

                if ($required_solde > $user_solde_available) {
                    return response()->json(['msg' => 'Votre solde est insuffissant !'], 401);
                }
            }

            // Prepare bulk insert data
            $multiCodeInserts = [];
            $userActiveCodeInserts = [];
            $now = now();

            foreach ($validCodes as $i => $code) {
                $code = trim($code);

                $multiCodeInserts[] = [
                    'len' => strlen($code),
                    'name' => $request['name'],
                    'number' => $code,
                    'pack' => $request['pack'],
                    'days' => "{$duration} days",
                    'max' => count($array1),
                    'user_id' => $user_id,
                    'package_id' => $request['pack'],
                    'notes' => $request->notes ?? 'iActive',
                    'pack' => json_encode($pack_list),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $userActiveCodeInserts[] = [
                    'member_id' => $user_id,
                    'created_by' => $user_id,
                    'username' => $code,
                    'password' => isset($passwords[$i]) ? $passwords[$i] : $this->generateSinglePassword(),
                    'admin_notes' => $user_type == 'Admin' ? 'iActive' : '',
                    'reseller_notes' => $user_type != 'Admin' ? 'iActive' : '',
                    'package_id' => $request->pack,
                    'duration_p' => $duration,
                    'duration_in' => 'days',
                    'bouquet' => $request->bouquets,
                    'is_trial' => $mypackage->is_trial,
                    'created_at' => $now,
                    'typecode' => 2,
                    'output' => '["m3u8","ts","rtmp"]',
                ];
            }

            // Perform bulk inserts
            if (!empty($multiCodeInserts)) {
                // Insert in chunks to avoid memory issues
                $chunkSize = 100;
                foreach (array_chunk($multiCodeInserts, $chunkSize) as $chunk) {
                    MultiCode::insert($chunk);
                }

                foreach (array_chunk($userActiveCodeInserts, $chunkSize) as $chunk) {
                    DB::connection('mysql2')->table('users_activecode')->insert($chunk);
                }
            }

            // Update user balance and create statistics
            if ($user_type != 'Admin' && $validCodesCount > 0) {
                $totalCost = $validCodesCount * $costMultiplier;
                $balanceField = $mypackage->is_trial ? 'solde_test' : 'solde';

                $user->decrement($balanceField, $totalCost);

                ResellerStatistic::create([
                    'reseller_id' => $user_id,
                    'solde' => $totalCost,
                    'operation' => 0,
                    'operation_name' => 'mass_code',
                    'slug' => 'create',
                ]);
            }

            DB::commit();
            return response()->json(['inserted' => $validCodesCount, 'code' => $code], 200);

        } catch (\Throwable $th) {
            DB::rollback();
            Log::error('Store method error: ' . $th->getMessage(), [
                'user_id' => $user_id ?? null,
                'pack' => $request['pack'] ?? null,
                'trace' => $th->getTraceAsString()
            ]);
            return response()->json(['error' => 'An error occurred!'], 500);
        }
    }

    private function generatePasswords(int $count): array
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $charLength = strlen($chars);
        $passwords = [];

        for ($i = 0; $i < $count; $i++) {
            $password = '';
            for ($j = 0; $j < 15; $j++) {
                $password .= $chars[random_int(0, $charLength - 1)];
            }
            $passwords[] = $password;
        }

        return $passwords;
    }

    private function generateSinglePassword(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $password = '';
        for ($i = 0; $i < 15; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\MultiCode  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        if (Auth::user()->type != "Admin") {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MultiCode::whereIn('user_id', $res)->where('id', $id)->first();
            if ($is_owner) {
            } else {
                return response(['message' => 'Wrong user'], 403);
            }
        }

        $multi = MultiCode::whereId($id)->first();
        request()->validate([
            'number' => Rule::unique('multi_codes')->ignore($multi->number, 'number'),
            'name' => 'required'
        ]);

        $current = DB::table('multi_codes')->where('id', $id)->first();

        $xtream = DB::connection('mysql2')->table('users_activecode')->where('username', $current->number)->first();
        if ($xtream) {
        } else {
            $xtream = DB::connection('mysql2')->table('users')->where('username', $current->number)->first();
        }

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $sld = User::find($user_id);

        $pack = DB::connection('mysql2')->table('packages')->find($request->pack);

        if ($request->pack != $current->package_id) {

            if ($pack->is_trial == "1") {
                $duration_p = $pack->trial_duration;
                $duration_in = $pack->trial_duration_in;
            } else {
                $duration_p = $pack->official_duration;
                $duration_in = $pack->official_duration_in;
            }

            // if($request->is_trial){
            //     $duration_p = $request->trial_duration;
            //     $duration_in = $request->trial_duration_in;
            // }else {
            //     $duration_p =$request->duration;
            //     $duration_in = $request->duration_in;
            // }

            if ($duration_p == '1' && $duration_in == 'years') {
                $duration_p = '365';
                $duration_in = 'days';
            } else if ($duration_p == '1' && $duration_in == 'months') {
                $duration_p = '30';
                $duration_in = 'days';

            } else if ($duration_p == '3' && $duration_in == 'months') {
                $duration_p = '90';
                $duration_in = 'days';
            } else if ($duration_p == '6' && $duration_in == 'months') {
                $duration_p = '180';
                $duration_in = 'days';
            } else if ($duration_p == '24' && $duration_in == 'hours') {
                $duration_p = '1';
                $duration_in = 'days';
            } else if ($duration_p == '10' && $duration_in == 'days') {
                $duration_p = '10';
                $duration_in = 'days';
            } else if ($duration_p == '2' && $duration_in == 'months') {
                $duration_p = '60';
                $duration_in = 'days';
            }

            $days = $duration_p . ' ' . $duration_in;

            $time = Null;

            $exp_date = Null;
            $mac = '';

        } else {
            $days = $current->days;
            $time = $current->time;
            $duration_p = $xtream->duration_p;
            $duration_in = $xtream->duration_in;
            $exp_date = $xtream->exp_date;
        }

        MultiCode::whereId($id)->update([
            'len' => $request['len'],
            'name' => $request['name'],
            'number' => $request['number'],
            'package_id' => $request['pack'],
            // 'days'              => $days,
            'user_id' => $user_id,
            'notes' => $request['notes'] ? $request['notes'] : 'iActive',
            'pack' => json_encode($request['pack_list']),
        ]);


        if ($user_type != 'Admin') {

            if ($request->notes !== null) {
                $kk = $request->notes;
                $var = '';
            } else {
                $request->notes = 'iActive';
                $var = '';
            }
        } else {
            if ($request->notes !== null) {
                $var = $request->notes;
                $kk = '';
            } else {
                $request->notes = 'iActive';
                $kk = '';
            }
        }
        $check_usact = DB::connection('mysql2')->table('users')->where('username', $current->number)->first();
        if ($check_usact) {
            DB::connection('mysql2')->table('users')->where('username', $current->number)
                ->update([
                        // 'macadress'  => $request['mac'],
                        'package_id' => $request['pack'],
                        'username' => $request['number'],
                        'admin_notes' => $kk,
                        'reseller_notes' => $var,
                        // 'duration_p'  =>   $duration_p,	
                        // 'duration_in' =>   $duration_in,
                        'bouquet' => $request->bouquets,
                        'is_trial' => $pack->is_trial
                    ]);
        } else {
            DB::connection('mysql2')->table('users_activecode')->where('username', $current->number)
                ->update([
                        'macadress' => $request['mac'],
                        'package_id' => $request['pack'],
                        'username' => $request['number'],
                        'admin_notes' => $kk,
                        'reseller_notes' => $var,
                        // 'duration_p'  =>   $duration_p,	
                        // 'duration_in' =>   $duration_in,
                        'bouquet' => $request->bouquets,
                        'is_trial' => $pack->is_trial
                    ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\MultiCode  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, $type)
    {

        if (Auth::user()->type != "Admin") {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MultiCode::whereIn('user_id', $res)->where('id', $id)->first();
            if ($is_owner) {
            } else {
                return response(['message' => 'Wrong user'], 403);
            }
        }

        $user_type = Auth::user()->type;
        $curr = DB::table('multi_codes')->where('id', $id)->first();
        if ($type == 'delete') {
            MultiCode::whereId($id)->delete();
            DB::connection('mysql2')->table('users')->where('username', $curr->number)->delete();
            $get_user = DB::connection('mysql2')->table('users')->where('username', $curr->number)->first();
            if ($get_user) {
                return DB::connection('mysql2')->table('users')->where('username', $curr->number)->update(
                    [
                        'enabled' => 0,
                    ]
                );
            } else {
                return DB::connection('mysql2')->table('users_activecode')->where('username', $curr->number)->update(
                    [
                        'enabled' => 0,
                    ]
                );
            }
        } else {
            MultiCode::whereId($id)->update([
                'enabled' => 0,

            ]);
            $get_user = DB::connection('mysql2')->table('users')->where('username', $curr->number)->first();
            if ($get_user) {
                return DB::connection('mysql2')->table('users')->where('username', $curr->number)->update(
                    [
                        'enabled' => 0,
                    ]
                );
            } else {
                return DB::connection('mysql2')->table('users_activecode')->where('username', $curr->number)->update(
                    [
                        'enabled' => 0,
                    ]
                );
            }
        }

    }

    public function enableMassCodeD($number)
    {

        if (Auth::user()->type != "Admin") {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MultiCode::whereIn('user_id', $res)->where('number', $number)->first();
            if ($is_owner) {
            } else {
                return response(['message' => 'Wrong user'], 403);
            }
        }

        MultiCode::where('number', $number)->update([
            'enabled' => 1,

        ]);
        $get_user = DB::connection('mysql2')->table('users')->where('username', $number)->first();
        if ($get_user) {
            return DB::connection('mysql2')->table('users')->where('username', $number)->update(
                [
                    'enabled' => 1,
                ]
            );
        } else {
            return DB::connection('mysql2')->table('users_activecode')->where('username', $number)->update(
                [
                    'enabled' => 1,
                ]
            );
        }
    }

    public function resetMac($id)
    {

        if (Auth::user()->type != "Admin") {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MultiCode::whereIn('user_id', $res)->where('id', $id)->first();
            if ($is_owner) {
            } else {
                return response(['message' => 'Wrong user'], 403);
            }
        }

        $current = DB::table('multi_codes')->where('id', $id)->first();
        MultiCode::whereId($id)->update(['mac' => '']);
        DB::connection('mysql2')->table('users')->where('users.username', $current->number)->update(['users.macadress' => 'Mac Reseted']);
    }

    public function Renew(Request $request, $code)
    {

        if (Auth::user()->type != "Admin") {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MultiCode::whereIn('user_id', $res)->where('number', $code)->first();
            if ($is_owner) {
            } else {
                return response(['message' => 'Wrong user'], 403);
            }
        }

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $user_pack = Auth::user()->package_id;
        $oo = "[$user_pack]";
        $yy = json_decode('[' . $oo . ']', true);

        // if($user_type != 'Admin'){            
        //     $dd = DB::connection('mysql2')->table('packages')->select('packages.*')
        //     ->whereIn('packages.id' , $yy[0])->get();
        // }else {
        //     $dd = DB::connection('mysql2')->table('packages')->select('packages.*')->get();
        // }
        // $getM = DB::table('multi_codes')->where('number', $code)->first();
        // $pack_id = $getM->package_id;
        // $getP = DB::connection('mysql2')->table('packages')->select('packages.*')
        //     ->where('packages.id' , $pack_id)->first();
        // if($getP->is_trial == 1) {
        //     foreach ($dd as $pack) {
        //         if($pack->official_duration == '365' || $pack->official_duration == '366') {
        //             $pack_id = $pack->id;
        //         }
        //     }
        // }
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $sld = User::find($user_id);




        $ss = 1;
        $days = 365;
        if (intval($request->month) == 30) {
            $ss = 0.1;
            $days = 30;
        } else if (intval($request->month) == 90) {
            $ss = 0.3;
            $days = 90;
        } else if (intval($request->month) == 180) {
            $ss = 0.60;
            $days = 180;
        }

        if ($user_type != 'Admin') {
            if ($sld->solde - $ss < 0) {
                return response()->json(['msg' => 'solde'], 401);
            }
        }

        // $expiredate= strtotime( "+12 month" );
        // $string_expiredate = date("Y-m-d", $expiredate);

        $current = DB::table('multi_codes')->where('number', $code)->first();
        $old = $current->time;
        if ($current->time == null) {
            // $old = Date('Y/m/d H:i:s', strtotime("+".$current->days));
            $c_user = DB::connection('mysql2')->table('users')->where('users.username', $current->number)->first();
            if ($c_user) {
                $old = Date('Y/m/d H:i:s', $c_user->exp_date);
            } else {
                $old = Date('Y/m/d H:i:s', strtotime("+" . $current->days));
            }
        } else {
            $old = $current->time;
        }

        $ee = new Carbon($old);
        if ($ee > Carbon::now()) {
        } else {
            $old = Carbon::now();
        }
        $date = new Carbon($old);
        $expire = $date->addDays(intval($days));
        $exp = $expire->format('Y-m-d H:i:s');

        $now = Carbon::now();
        $length = $now->diff($exp)->days;

        DB::table('multi_codes')->where('number', $code)->update([
            'days' => $length . ' ' . "days",
            'time' => $current->time == null ? null : $exp,
            // 'package_id' => $pack_id 
        ]);


        DB::connection('mysql2')->table('users')->where('users.username', $code)->update(
            [

                // 'users.duration_p'  =>   '365',	
                // 'users.duration_in' =>   'days',
                'users.exp_date' => strtotime($exp),
                'is_trial' => 0,
                // 'package_id' => $pack_id,
                'is_mag' => 0


            ]

        );

        DB::connection('mysql2')->table('users_activecode')->where('users_activecode.username', $code)->update(
            [
                'duration_p' => $length,
                'duration_in' => 'days',
                'is_trial' => 0,
                // 'package_id' => $pack_id,
                'is_mag' => 0
            ]

        );

        if ($user_type != 'Admin') {
            $ss = 1;
            if (intval($request->month) == 30) {
                $ss = 0.1;
            } else if (intval($request->month) == 90) {
                $ss = 0.3;
            } else if (intval($request->month) == 180) {
                $ss = 0.60;
            }

            if ($user_type != 'Admin') {
                if ($sld->solde - $ss < 0) {
                    return response()->json(['msg' => 'solde'], 401);
                }
            }
            $sld->update([

                'solde' => $sld->solde - $ss

            ]);

            ResellerStatistic::create([
                'reseller_id' => $user_id,
                'solde' => $ss,
                'operation' => 0,
                'operation_name' => 'mass_code',
                'slug' => 'renew'
            ]);
        }
    }

    public function getSolde()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        // $user->solde = str_replace('\u', '', $user->solde);
        // $user->solde = preg_replace('/[^\p{L}\s]/u','', $user->solde);
        $user->solde = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $user->solde);
        $user->solde_test = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $user->solde_test);
        if ($user->type != 'Admin') {
            return response()->json(['solde' => floatval($user->solde), 'solde_test' => floatval($user->solde_test)]);
        } else {
            return response()->json(['solde' => 1000]);
        }

    }

    public function changeDays(Request $request, $id)
    {

        if (Auth::user()->type != "Admin") {
            abort(401);
        }

        if (Auth::user()->type != "Admin") {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MultiCode::whereIn('user_id', $res)->where('id', $id)->first();
            if ($is_owner) {
            } else {
                return response(['message' => 'Wrong user'], 403);
            }
        }

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $user_solde = Auth::user()->solde;

        $sld = User::find($user_id);
        $current = DB::table('multi_codes')->find($id);
        $old = $current->time;

        $new_date = explode(" ", $request->days);

        $date = Carbon::now();
        $expire = $date->addDays($new_date[0]);
        $exp = $expire->format('Y-m-d H:i:s');

        $now = Carbon::now();
        $length = $now->diff($exp)->days + 1;

        DB::table('multi_codes')->whereId($id)->update([
            'days' => $length . ' ' . "days",
            'time' => $exp,
        ]);

        $user_ex = DB::connection('mysql2')->table('users')->where('users.username', $current->number)->first();
        if ($user_ex) {
            DB::connection('mysql2')->table('users')->where('users.username', $current->number)->update(
                [
                    // 'users.duration_p'  =>    $length,	
                    // 'users.duration_in' =>   'days',
                    'users.exp_date' => strtotime($exp),
                    'is_mag' => 0
                ]

            );
        } else {
            DB::connection('mysql2')->table('users_activecode')->where('username', $current->number)->update(
                [
                    'users_activecode.duration_p' => $length,
                    'users_activecode.duration_in' => 'days',
                    'users_activecode.exp_date' => strtotime($exp),
                    'is_mag' => 0
                ]

            );
        }
    }

    public function byReseller(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        if ($user_type !== 'Admin') {

            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        } else {
            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->with('user')->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);

            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();

            if ($user_activecode) {
                $active->selected_bouquets = json_decode($user_activecode->bouquet);
            } elseif ($user_users) {
                $active->selected_bouquets = json_decode($user_users->bouquet);
            }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            if ($userT) {
                $active->is_trial = $userT->is_trial;
                if ($active->mac == "" || $active->mac == null) {
                    if ($userT->macadress != "" || $userT->macadress != null) {
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                    if ($userT->exp_date != "" || $userT->exp_date != null) {
                        $active->time = date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }

                // $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if (count($users_activity_now) > 0) {
                    $active->online = 1;
                }
                // $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if (count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if ($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if ($active->online == 1) {
                                $active->latency = (100 - $activity->divergence) / 20;
                            }

                            $date1 = date_create(date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2 = date_create(date("Y-m-d H:i:s"));
                            // else
                            // $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date = $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if ($active->stream_id != '') {
                    $channels = DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if ($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }

                }
            }
        }
        return Response()->json($MultiCode);
    }

    public function expiredItems()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();

        $users = DB::connection('mysql2')->table('users')->where('exp_date', '<', strtotime(date("Y/m/d H:i:s")))->pluck('username');

        $subArray = [];
        foreach ($subRes as $row) {
            array_push($subArray, $row->user_id);
        }
        array_push($subArray, $user_id);
        if ($user_type !== 'Admin') {
            $model = new MultiCode;
            $MultiCode = $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.user_id', $subArray);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if ($query)
                $MultiCode = $MultiCode->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        } else {
            $model = new MultiCode;
            $MultiCode = $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->with('user')->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('number', $users);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.number', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();

            if ($user_users) {
                $active->selected_bouquets = json_decode($user_users->bouquet);
            } elseif ($user_activecode) {
                $active->selected_bouquets = json_decode($user_activecode->bouquet);
            }
            if ($active->pack) {
                $active->pack = json_decode($active->pack);
            } else {
                $active->pack = '';
            }
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            $active->has_is_trial = false;
            if ($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                if ($active->mac == "" || $active->mac == null) {
                    if ($userT->macadress != "" || $userT->macadress != null) {
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                    if ($userT->exp_date != "" || $userT->exp_date != null) {
                        $active->time = date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today, $exp_time);
                        // $active->days = $new_days->format("%a days");
                        if (date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $userT->days = $new_days->format("%a days");
                        } else {
                            $userT->days = "0 days";
                        }
                    }
                }
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if (count($users_activity_now) > 0) {
                    $active->online = 1;
                }
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if (count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if ($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if ($active->online == 1) {
                                $active->latency = (100 - $activity->divergence) / 20;
                            }

                            $date1 = date_create(date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2 = date_create(date("Y-m-d H:i:s"));
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date = $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if ($active->stream_id != '') {
                    $channels = DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if ($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }

                }
            }
        }

        return Response()->json($MultiCode);
    }

    public function expiredItemsByUser(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $users = DB::connection('mysql2')->table('users')->where('exp_date', '<', strtotime(date("Y/m/d H:i:s")))->pluck('username');

        $query = request('query');

        if ($user_type !== 'Admin') {

            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        } else {
            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->with('user')->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();

            if ($user_activecode) {
                $active->selected_bouquets = json_decode($user_activecode->bouquet);
            } elseif ($user_users) {
                $active->selected_bouquets = json_decode($user_users->bouquet);
            }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if ($userT) {
                $active->is_trial = $userT->is_trial;
                if ($active->mac == "" || $active->mac == null) {
                    if ($userT->macadress != "" || $userT->macadress != null) {
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                    if ($userT->exp_date != "" || $userT->exp_date != null) {
                        $active->time = date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if (count($users_activity_now) > 0) {
                    $active->online = 1;
                }
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if (count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if ($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if ($active->online == 1) {
                                $active->latency = (100 - $activity->divergence) / 20;
                            }

                            $date1 = date_create(date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2 = date_create(date("Y-m-d H:i:s"));
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date = $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if ($active->stream_id != '') {
                    $channels = DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if ($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }

                }
            }
        }
        return Response()->json($MultiCode);
    }

    public function onlineItems()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();
        $subArray = [];
        foreach ($subRes as $row) {
            array_push($subArray, $row->user_id);
        }
        array_push($subArray, $user_id);

        $users_activity_now_pluck = DB::connection('mysql2')->table('con_activities')->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->pluck('user_id');
        $users = DB::connection('mysql2')->table('users')->whereIn('id', $users_activity_now_pluck)->pluck('username');

        if ($user_type !== 'Admin') {
            $model = new MultiCode;
            $MultiCode = $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.user_id', $subArray);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if ($query)
                $MultiCode = $MultiCode->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        } else {
            $model = new MultiCode;
            $MultiCode = $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->with('user')->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('number', $users);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.number', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();

            if ($user_users) {
                $active->selected_bouquets = json_decode($user_users->bouquet);
            } elseif ($user_activecode) {
                $active->selected_bouquets = json_decode($user_activecode->bouquet);
            }
            if ($active->pack) {
                $active->pack = json_decode($active->pack);
            } else {
                $active->pack = '';
            }
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            $active->has_is_trial = false;
            if ($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                if ($active->mac == "" || $active->mac == null) {
                    if ($userT->macadress != "" || $userT->macadress != null) {
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                    if ($userT->exp_date != "" || $userT->exp_date != null) {
                        $active->time = date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today, $exp_time);
                        // $active->days = $new_days->format("%a days");
                        if (date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $userT->days = $new_days->format("%a days");
                        } else {
                            $userT->days = "0 days";
                        }
                    }
                }
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if (count($users_activity_now) > 0) {
                    $active->online = 1;
                }
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if (count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if ($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if ($active->online == 1) {
                                $active->latency = (100 - $activity->divergence) / 20;
                            }

                            $date1 = date_create(date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2 = date_create(date("Y-m-d H:i:s"));
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date = $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if ($active->stream_id != '') {
                    $channels = DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if ($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }

                }
            }
        }

        return Response()->json($MultiCode);
    }

    public function onlineItemsByUser(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        $users_activity_now_pluck = DB::connection('mysql2')->table('con_activities')->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->pluck('user_id');
        $users = DB::connection('mysql2')->table('users')->whereIn('id', $users_activity_now_pluck)->pluck('username');

        if ($user_type !== 'Admin') {

            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        } else {
            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->with('user')->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();

            if ($user_activecode) {
                $active->selected_bouquets = json_decode($user_activecode->bouquet);
            } elseif ($user_users) {
                $active->selected_bouquets = json_decode($user_users->bouquet);
            }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if ($userT) {
                $active->is_trial = $userT->is_trial;
                if ($active->mac == "" || $active->mac == null) {
                    if ($userT->macadress != "" || $userT->macadress != null) {
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                    if ($userT->exp_date != "" || $userT->exp_date != null) {
                        $active->time = date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if (count($users_activity_now) > 0) {
                    $active->online = 1;
                }
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if (count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if ($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if ($active->online == 1) {
                                $active->latency = (100 - $activity->divergence) / 20;
                            }

                            $date1 = date_create(date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2 = date_create(date("Y-m-d H:i:s"));
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date = $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if ($active->stream_id != '') {
                    $channels = DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if ($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }

                }
            }
        }
        return Response()->json($MultiCode);
    }

    public function amolstExpiredItems()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();
        $subArray = [];
        foreach ($subRes as $row) {
            array_push($subArray, $row->user_id);
        }
        array_push($subArray, $user_id);

        $users = DB::connection('mysql2')->table('users')
            ->where('exp_date', '<=', strtotime(Date('Y/m/d H:i:s', strtotime("+2 days"))))
            ->where('exp_date', '>', strtotime(date("Y/m/d H:i:s")))
            ->pluck('username');

        if ($user_type !== 'Admin') {
            $model = new MultiCode;
            $MultiCode = $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.user_id', $subArray);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if ($query)
                $MultiCode = $MultiCode->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        } else {
            $model = new MultiCode;
            $MultiCode = $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->with('user')->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.number', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();

            if ($user_users) {
                $active->selected_bouquets = json_decode($user_users->bouquet);
            } elseif ($user_activecode) {
                $active->selected_bouquets = json_decode($user_activecode->bouquet);
            }
            if ($active->pack) {
                $active->pack = json_decode($active->pack);
            } else {
                $active->pack = '';
            }
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            $active->has_is_trial = false;
            if ($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                if ($active->mac == "" || $active->mac == null) {
                    if ($userT->macadress != "" || $userT->macadress != null) {
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                    if ($userT->exp_date != "" || $userT->exp_date != null) {
                        $active->time = date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today, $exp_time);
                        // $active->days = $new_days->format("%a days");
                        if (date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $userT->days = $new_days->format("%a days");
                        } else {
                            $userT->days = "0 days";
                        }
                    }
                }
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if (count($users_activity_now) > 0) {
                    $active->online = 1;
                }
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if (count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if ($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if ($active->online == 1) {
                                $active->latency = (100 - $activity->divergence) / 20;
                            }

                            $date1 = date_create(date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2 = date_create(date("Y-m-d H:i:s"));
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date = $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if ($active->stream_id != '') {
                    $channels = DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if ($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }

                }
            }
        }

        return Response()->json($MultiCode);
    }

    public function amolstExpiredItemsByUser(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        $users = DB::connection('mysql2')->table('users')
            ->where('exp_date', '<=', strtotime(Date('Y/m/d H:i:s', strtotime("+2 days"))))
            ->where('exp_date', '>', strtotime(date("Y/m/d H:i:s")))
            ->pluck('username');

        if ($user_type !== 'Admin') {

            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        } else {
            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->with('user')->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();

            if ($user_activecode) {
                $active->selected_bouquets = json_decode($user_activecode->bouquet);
            } elseif ($user_users) {
                $active->selected_bouquets = json_decode($user_users->bouquet);
            }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if ($userT) {
                $active->is_trial = $userT->is_trial;
                if ($active->mac == "" || $active->mac == null) {
                    if ($userT->macadress != "" || $userT->macadress != null) {
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                    if ($userT->exp_date != "" || $userT->exp_date != null) {
                        $active->time = date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if (count($users_activity_now) > 0) {
                    $active->online = 1;
                }
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if (count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if ($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if ($active->online == 1) {
                                $active->latency = (100 - $activity->divergence) / 20;
                            }

                            $date1 = date_create(date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2 = date_create(date("Y-m-d H:i:s"));
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date = $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if ($active->stream_id != '') {
                    $channels = DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if ($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }

                }
            }
        }
        return Response()->json($MultiCode);
    }

    public function trialItems()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();
        $subArray = [];
        foreach ($subRes as $row) {
            array_push($subArray, $row->user_id);
        }
        array_push($subArray, $user_id);

        $packages = DB::connection('mysql2')->table('packages')->where('is_trial', 1)->pluck('id');

        if ($user_type !== 'Admin') {
            $model = new MultiCode;
            $MultiCode = $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.user_id', $subArray);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->whereIn('multi_codes.package_id', $packages);
            if ($query)
                $MultiCode = $MultiCode->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        } else {
            $model = new MultiCode;
            $MultiCode = $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->with('user')->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.package_id', $packages);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.number', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();

            if ($user_users) {
                $active->selected_bouquets = json_decode($user_users->bouquet);
            } elseif ($user_activecode) {
                $active->selected_bouquets = json_decode($user_activecode->bouquet);
            }
            if ($active->pack) {
                $active->pack = json_decode($active->pack);
            } else {
                $active->pack = '';
            }
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            $active->has_is_trial = false;
            if ($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                if ($active->mac == "" || $active->mac == null) {
                    if ($userT->macadress != "" || $userT->macadress != null) {
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                    if ($userT->exp_date != "" || $userT->exp_date != null) {
                        $active->time = date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today, $exp_time);
                        // $active->days = $new_days->format("%a days");
                        if (date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $userT->days = $new_days->format("%a days");
                        } else {
                            $userT->days = "0 days";
                        }
                    }
                }
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if (count($users_activity_now) > 0) {
                    $active->online = 1;
                }
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if (count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if ($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if ($active->online == 1) {
                                $active->latency = (100 - $activity->divergence) / 20;
                            }

                            $date1 = date_create(date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2 = date_create(date("Y-m-d H:i:s"));
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date = $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if ($active->stream_id != '') {
                    $channels = DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if ($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }

                }
            }
        }

        return Response()->json($MultiCode);
    }

    public function trialItemsByUser(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        $packages = DB::connection('mysql2')->table('packages')->where('is_trial', 1)->pluck('id');

        if ($user_type !== 'Admin') {

            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->whereIn('multi_codes.package_id', $packages);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        } else {
            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->with('user')->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.package_id', $packages);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);

            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();

            if ($user_activecode) {
                $active->selected_bouquets = json_decode($user_activecode->bouquet);
            } elseif ($user_users) {
                $active->selected_bouquets = json_decode($user_users->bouquet);
            }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if ($userT) {
                $active->is_trial = $userT->is_trial;
                if ($active->mac == "" || $active->mac == null) {
                    if ($userT->macadress != "" || $userT->macadress != null) {
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                    if ($userT->exp_date != "" || $userT->exp_date != null) {
                        $active->time = date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if (count($users_activity_now) > 0) {
                    $active->online = 1;
                }
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if (count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if ($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if ($active->online == 1) {
                                $active->latency = (100 - $activity->divergence) / 20;
                            }

                            $date1 = date_create(date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2 = date_create(date("Y-m-d H:i:s"));
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date = $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if ($active->stream_id != '') {
                    $channels = DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if ($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }

                }
            }
        }
        return Response()->json($MultiCode);
    }

    public function disabledItems()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();
        $subArray = [];
        foreach ($subRes as $row) {
            array_push($subArray, $row->user_id);
        }
        array_push($subArray, $user_id);
        if ($user_type !== 'Admin') {
            $model = new MultiCode;
            $MultiCode = $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.user_id', $subArray);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->where('enabled', 0);
            if ($query)
                $MultiCode = $MultiCode->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        } else {
            $model = new MultiCode;
            $MultiCode = $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->with('user')->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->where('enabled', 0);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.number', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();

            if ($user_users) {
                $active->selected_bouquets = json_decode($user_users->bouquet);
            } elseif ($user_activecode) {
                $active->selected_bouquets = json_decode($user_activecode->bouquet);
            }
            if ($active->pack) {
                $active->pack = json_decode($active->pack);
            } else {
                $active->pack = '';
            }
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            $active->has_is_trial = false;
            if ($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                if ($active->mac == "" || $active->mac == null) {
                    if ($userT->macadress != "" || $userT->macadress != null) {
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                    if ($userT->exp_date != "" || $userT->exp_date != null) {
                        $active->time = date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today, $exp_time);
                        // $active->days = $new_days->format("%a days");
                        if (date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $userT->days = $new_days->format("%a days");
                        } else {
                            $userT->days = "0 days";
                        }
                    }
                }
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if (count($users_activity_now) > 0) {
                    $active->online = 1;
                }
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if (count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if ($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if ($active->online == 1) {
                                $active->latency = (100 - $activity->divergence) / 20;
                            }

                            $date1 = date_create(date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2 = date_create(date("Y-m-d H:i:s"));
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date = $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if ($active->stream_id != '') {
                    $channels = DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if ($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }

                }
            }
        }

        return Response()->json($MultiCode);
    }

    public function disabledItemsByUser(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        if ($user_type !== 'Admin') {

            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->where('enabled', 0);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        } else {
            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->with('user')->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->where('enabled', 0);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();

            if ($user_activecode) {
                $active->selected_bouquets = json_decode($user_activecode->bouquet);
            } elseif ($user_users) {
                $active->selected_bouquets = json_decode($user_users->bouquet);
            }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if ($userT) {
                $active->is_trial = $userT->is_trial;
                if ($active->mac == "" || $active->mac == null) {
                    if ($userT->macadress != "" || $userT->macadress != null) {
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                    if ($userT->exp_date != "" || $userT->exp_date != null) {
                        $active->time = date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if (count($users_activity_now) > 0) {
                    $active->online = 1;
                }
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if (count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if ($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if ($active->online == 1) {
                                $active->latency = (100 - $activity->divergence) / 20;
                            }

                            $date1 = date_create(date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2 = date_create(date("Y-m-d H:i:s"));
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date = $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if ($active->stream_id != '') {
                    $channels = DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if ($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }

                }
            }
        }
        return Response()->json($MultiCode);
    }

    public function enabledItems()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();
        $subArray = [];
        foreach ($subRes as $row) {
            array_push($subArray, $row->user_id);
        }
        array_push($subArray, $user_id);
        if ($user_type !== 'Admin') {
            $model = new MultiCode;
            $MultiCode = $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.user_id', $subArray);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->where('enabled', 1);
            if ($query)
                $MultiCode = $MultiCode->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        } else {
            $model = new MultiCode;
            $MultiCode = $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->with('user')->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('enabled', 1);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.number', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                    ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();

            if ($user_users) {
                $active->selected_bouquets = json_decode($user_users->bouquet);
            } elseif ($user_activecode) {
                $active->selected_bouquets = json_decode($user_activecode->bouquet);
            }
            if ($active->pack) {
                $active->pack = json_decode($active->pack);
            } else {
                $active->pack = '';
            }
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            $active->has_is_trial = false;
            if ($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                if ($active->mac == "" || $active->mac == null) {
                    if ($userT->macadress != "" || $userT->macadress != null) {
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                    if ($userT->exp_date != "" || $userT->exp_date != null) {
                        $active->time = date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today, $exp_time);
                        // $active->days = $new_days->format("%a days");
                        if (date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $userT->days = $new_days->format("%a days");
                        } else {
                            $userT->days = "0 days";
                        }
                    }
                }
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if (count($users_activity_now) > 0) {
                    $active->online = 1;
                }
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if (count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if ($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if ($active->online == 1) {
                                $active->latency = (100 - $activity->divergence) / 20;
                            }

                            $date1 = date_create(date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2 = date_create(date("Y-m-d H:i:s"));
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date = $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if ($active->stream_id != '') {
                    $channels = DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if ($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }

                }
            }
        }

        return Response()->json($MultiCode);
    }

    public function enabledItemsByUser(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        if ($user_type !== 'Admin') {

            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);
            $MultiCode = $MultiCode->where('enabled', 1);
            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        } else {
            $model = new MultiCode;
            $MultiCode = $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('enabled', 1);
            $MultiCode = $MultiCode->with('user')->select('multi_codes.*', 'users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.deleted_at', '=', Null);

            if ($query)
                $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('multi_codes.number', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.mac', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.name', 'LIKE', "%{$query}%")
                        ->orWhere('multi_codes.notes', 'LIKE', "%{$query}%");
                });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();

            if ($user_activecode) {
                $active->selected_bouquets = json_decode($user_activecode->bouquet);
            } elseif ($user_users) {
                $active->selected_bouquets = json_decode($user_users->bouquet);
            }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if ($userT) {
                $active->is_trial = $userT->is_trial;
                if ($active->mac == "" || $active->mac == null) {
                    if ($userT->macadress != "" || $userT->macadress != null) {
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                    if ($userT->exp_date != "" || $userT->exp_date != null) {
                        $active->time = date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if (count($users_activity_now) > 0) {
                    $active->online = 1;
                }
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if (count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if ($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if ($active->online == 1) {
                                $active->latency = (100 - $activity->divergence) / 20;
                            }

                            $date1 = date_create(date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2 = date_create(date("Y-m-d H:i:s"));
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date = $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if ($active->stream_id != '') {
                    $channels = DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if ($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }

                }
            }
        }
        return Response()->json($MultiCode);
    }


    public function get_code(Request $request)
    {

        // if(!str_contains(response()->json($request->userAgent()),'iactive')) {
        //     abort(401);
        // }

        $count = $request->count;
        $start = "220";
        $len = 10;
        $package_id = "141";
        $pack = [
            231,
            70,
            71,
            116,
            118,
            76,
            64,
            82,
            83,
            84,
            85,
            167,
            130,
            143,
            86,
            87,
            90,
            89,
            75,
            74,
            98,
            91,
            120,
            121,
            93,
            81,
            142,
            95,
            97,
            96,
            100,
            101,
            149,
            102,
            104,
            127,
            103,
            128,
            105,
            144,
            179,
            155,
            109,
            134,
            133,
            107,
            136,
            114,
            111,
            135,
            113,
            115,
            174,
            117,
            108,
            38,
            151,
            186,
            187,
            188,
            196,
            197,
            158
        ];
        $pack_text = "[231,70,71,116,118,76,64,82,83,84,85,167,130,143,86,87,90,89,75,74,98,91,120,121,93,81,142,95,97,96,100,101,149,102,104,127,103,128,105,144,179,155,109,134,133,107,136,114,111,135,113,115,174,117,108,38,151,186,187,188,196,197,158]";
        $duration = "365 days";
        $user_id = "7461";

        $existingCodes = array_merge(
            MultiCode::where('number', 'like', $start . '%')->pluck('number')->toArray(),
            ActiveCode::where('number', 'like', $start . '%')->pluck('number')->toArray(),
            DB::connection('mysql2')->table('users')->where('username', 'like', $start . '%')->pluck('username')->toArray()
        );
        $existingCodes = array_flip($existingCodes);

        $array = [];
        while (count($array) < $count) {
            $random_number = str_shuffle((string) mt_rand(0, PHP_INT_MAX));
            $randome_code = substr($random_number, 0, $len);
            $rand = $start . $randome_code;

            if (!isset($existingCodes[$rand]) && !in_array($rand, $array)) {
                $array[] = $rand;
                $existingCodes[$rand] = true;
            }
        }

        sort($array);

        DB::beginTransaction();
        try {
            $insertedCodes = 0;

            foreach ($array as $code) {
                MultiCode::create([
                    'len' => $len,
                    'name' => 'Generated Code Batch',
                    'number' => $code,
                    'pack' => json_encode($pack),
                    'days' => $duration,
                    'max' => $count,
                    'user_id' => $user_id,
                    'package_id' => $package_id,
                    'notes' => 'BOX',
                ]);

                DB::connection('mysql2')->table('users_activecode')->insert([
                    'member_id' => $user_id,
                    'created_by' => $user_id,
                    'username' => $code,
                    'password' => $this->generatePassword(),
                    'admin_notes' => 'BOX',
                    'reseller_notes' => '',
                    'package_id' => $package_id,
                    'duration_p' => 365,
                    'duration_in' => 'days',
                    'bouquet' => $pack_text,
                    'is_trial' => false,
                    'created_at' => now(),
                    'typecode' => 2,
                    'output' => '["m3u8","ts","rtmp"]',
                ]);

                $insertedCodes++;
            }
            DB::commit();
            return response()->json(['inserted' => $insertedCodes, 'codes' => $array], 200);

        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['error' => 'An error occurred while inserting codes!', 'codes' => $array], 500);
        }
    }

    /**
     * Generate 15-character password with uppercase, lowercase, and numbers
     */
    private function generatePassword()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $password = '';
        for ($i = 0; $i < 15; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
    }

}
