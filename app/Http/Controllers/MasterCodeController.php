<?php

namespace App\Http\Controllers;

use App\MasterCode;
use App\User;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use DB;

class MasterCodeController extends Controller
{
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

        // Build the main query
        if ($user_type !== 'Admin') {
            $MasterCode = MasterCode::join('users', 'master_codes.user_id', '=', 'users.id')
                ->select('master_codes.*', 'users.name AS UserName')
                ->where('master_codes.user_id', $user_id)
                ->whereNull('master_codes.deleted_at');

            if ($query) {
                $MasterCode = $MasterCode->where(function ($q) use ($query) {
                    $q->where('master_codes.number', 'LIKE', "%{$query}%")
                        ->orWhere('master_codes.mac', 'LIKE', "%{$query}%")
                        ->orWhere('master_codes.name', 'LIKE', "%{$query}%")
                        ->orWhere('master_codes.notes', 'LIKE', "%{$query}%");
                });
            }
        } else {
            $MasterCode = MasterCode::join('users', 'master_codes.user_id', '=', 'users.id')
                ->select('master_codes.*', 'users.name AS UserName')
                ->where('master_codes.user_id', $user_id)
                ->whereNull('master_codes.deleted_at');

            // FIX: Proper grouping for orWhere (was breaking deleted_at and user_id conditions)
            if ($query) {
                $MasterCode = $MasterCode->where(function ($q) use ($query) {
                    $q->where('master_codes.number', 'LIKE', "%{$query}%")
                        ->orWhere('master_codes.mac', 'LIKE', "%{$query}%")
                        ->orWhere('master_codes.name', 'LIKE', "%{$query}%")
                        ->orWhere('master_codes.notes', 'LIKE', "%{$query}%");
                });
            }
        }

        $MasterCode = $MasterCode->orderBy('master_codes.id', 'DESC')->paginate(20);

        // Early return if no results
        if ($MasterCode->isEmpty()) {
            return response()->json($MasterCode);
        }

        // Collect all numbers and package IDs for batch queries
        $numbers = $MasterCode->pluck('number')->toArray();
        $packageIds = $MasterCode->pluck('package_id')->unique()->toArray();

        // BATCH 1: Get remote users by username
        $remoteUsers = DB::connection('mysql2')->table('users')
            ->select('username', 'is_trial', 'exp_date', 'macadress', 'id', 'bouquet')
            ->whereIn('username', $numbers)
            ->get()
            ->keyBy('username');

        // BATCH 2: Get users_master_code for bouquets fallback
        $masterCodes = DB::connection('mysql2')->table('users_master_code')
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

        $now = now();

        // Process each code
        foreach ($MasterCode as $active) {
            // Defaults
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->selected_bouquets = [];
            $active->has_is_trial = false;
            $active->pack = $active->pack ? json_decode($active->pack) : '';

            // Get bouquets from remote user or master_code
            $remoteUser = $remoteUsers->get($active->number);
            $masterCodeEntry = $masterCodes->get($active->number);

            if ($remoteUser && $remoteUser->bouquet) {
                $active->selected_bouquets = json_decode($remoteUser->bouquet);
            } elseif ($masterCodeEntry && $masterCodeEntry->bouquet) {
                $active->selected_bouquets = json_decode($masterCodeEntry->bouquet);
            }

            if ($remoteUser) {
                $active->is_trial = $remoteUser->is_trial;
                $active->has_is_trial = true;
                $active->exist = $remoteUser->exp_date;

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

                    $activityDate = Carbon::createFromTimestamp($activity->date_start);
                    $active->last_seen_date = $now->diff($activityDate)->format('%hh %im %ss');

                    // Stream name
                    if ($activity->stream_id && $streams->has($activity->stream_id)) {
                        $active->stream_name = $streams->get($activity->stream_id)->stream_display_name;
                    }
                }
            }
        }

        return response()->json($MasterCode);
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


    public function getRequest()
    {
        request()->validate(
            [
                'number' => 'required|unique:master_codes',
                'name' => 'required',
                'len' => 'required',
                'mac' => 'required',
                'myarray' => 'max:20000',
            ],
            [

                'number.required' => 'please Random Active Codes',
                'name.required' => 'please put your name',
                'len.required' => 'please choose length of code',
                'mac.required' => 'please put mac adress',
                'myarray.max' => 'Max Adress Mac Is 20000',
            ]
        );
    }

    public function store(Request $request)
    {
        request()->validate(
            [
                'number' => 'required|unique:master_codes',
                'name' => 'required',
                'len' => 'required',
                'pack' => 'required',
                'mac' => 'required',
                'myarray' => 'max:20000',
            ],
            [

                'number.required' => 'please Random Active Codes',
                'name.required' => 'please put your name',
                'len.required' => 'please choose length of code',
                'pack.required' => 'please Choose Package',
                'mac.required' => 'please put mac adress',
                'myarray.max' => 'Max Adress Mac Is 20000',
            ]
        );

        DB::beginTransaction();
        try {
            $data = explode("\n", $request->mac);
            $array_mac = str_replace(":", "", $data);
            $now = date("Y-m-d H:i:s");
            $now = strtotime("$now");
            $created_at = $now;

            $user = Auth::user();
            $user_id = auth()->id();
            $user_type = Auth::user()->type;

            if ($request->is_trial) {
                $duration_p = $request->trial_duration;
                $duration_in = $request->trial_duration_in;

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

                $i = $duration_p;
                $date = Carbon::now();
                $expire = $date->addDays($i);
                $exp = $expire->format('Y-m-d H:i:s');

            } else {
                $duration_p = $request->duration;
                $duration_in = $request->duration_in;

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
                $i = $duration_p;
                $date = Carbon::now();
                $expire = $date->addDays($i);
                $exp = $expire->format('Y-m-d H:i:s');
            }



            $sld = User::find($user_id);

            $i = 0;

            if ($user_type != 'Admin') {
                if ($request->notes !== null) {
                    $kk = $request->notes;
                    $var = '';
                } else {
                    $kk = 'iActive';
                    $var = '';
                }
            } else {
                if ($request->notes !== null) {
                    $var = $request->notes;
                    $kk = '';
                } else {
                    $var = 'iActive';
                    $kk = '';
                }
            }

            DB::connection('mysql2')->table('users_master_code')->insert(
                [
                    'member_id' => $user_id,
                    'created_by' => $user_id,
                    'username' => $request->number,
                    'password' => $request->password,
                    // 'macadress'    =>   $array_mac[$i],
                    'admin_notes' => $var,
                    'reseller_notes' => $kk,
                    'package_id' => $request->pack,
                    'duration_p' => $duration_p,
                    'duration_in' => $duration_in,
                    'bouquet' => $request->bouquets,
                    'is_trial' => $request->is_trial,
                    'allowed_ips' => '',
                    'allowed_ua' => '',
                    'created_at' => $created_at,
                    // 'typecode'    =>   1,
                    'exp_date' => strtotime($exp),
                    'access_output_id' => '[1,2,3]'
                ]
            );
            $user_master = DB::connection('mysql2')->table('users_master_code')->where('username', $request->number)->where('password', $request->password)->first();

            foreach ($array_mac as $m) {

                MasterCode::create([
                    'len' => $request['len'],
                    'name' => $request['name'],
                    'number' => $request['number'],
                    'mac' => $array_mac[$i],
                    'days' => $duration_p . ' ' . $duration_in,
                    'user_id' => $user_id,
                    'notes' => $request['notes'] ? $request['notes'] : 'iActive',
                    'package_id' => $request['pack']
                ]);

                DB::connection('mysql2')->table('mastercode_devices')->insert(
                    [
                        'user_id' => $user_master->id,
                        'mac' => $array_mac[$i],
                        'exp_date_master' => strtotime($exp),
                        'temoin' => 'NO ACTIVE'

                    ]
                );

                if ($user_type != 'Admin') {
                    $sld->update([
                        'solde' => $sld->solde - 1
                    ]);
                }
                $i++;
            }
            DB::commit();
            return response()->json(['finish' => 'true']);
        } catch (\Throwable $th) {
            DB::rollback();
            abort(401);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\ActiveCode  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function show(ActiveCode $activeCode)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\ActiveCode  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function edit(ActiveCode $activeCode)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ActiveCode  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, User $user)
    {

        if (Auth::user()->type != "Admin") {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MasterCode::whereIn('user_id', $res)->where('id', $id)->first();
            if ($is_owner) {
            } else {
                return response(['message' => 'Wrong user'], 403);
            }
        }

        // dd($request->all());

        request()->validate([
            'mac' => 'required|unique:master_codes,id',
            // 'name' => 'required',
            // 'len' => 'required',
            // 'pack' => 'required',
        ]);

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $m = $request->mac;
        $mac = str_replace(":", "", $m);

        $current = DB::table('master_codes')->where('id', $id)->first();


        $xtream = DB::connection('mysql2')->table('users')->where('users.username', $current->number)->where('users.macadress', $current->mac)->first();

        $sld = User::find($user_id);

        if ($request->check === true) {

            if ($user_type != 'Admin') {

                if ($request->notes !== null) {
                    $kk = $request->notes;
                    $var = '';
                    //  $kk=1;
                } else {
                    // $kk=0;
                    $kk = 'iActive';
                    $var = '';
                }
            } else {

                if ($request->notes !== null) {
                    $var = $request->notes;
                    $kk = '';
                    // $var=1;
                } else {
                    // $var=0;
                    $var = 'iActive';
                    $kk = '';
                }

            }


            MasterCode::where('number', $current->number)->update([
                'len' => $request['len'],
                'name' => $request['name'],
                'number' => $request['number'],
                'package_id' => $request['pack'],
                // 'days'              => $days,
                // 'time'              => $time,
                'user_id' => $user_id,
                'notes' => $request['notes'] ? $request['notes'] : 'iActive',
            ]);



            DB::connection('mysql2')->table('users_master_code')->where('users_master_code.username', $request['number'])->update([
                'users_master_code.package_id' => $request['pack'],
                'users_master_code.username' => $request['number'],
                'users_master_code.admin_notes' => $var,
                'users_master_code.reseller_notes' => $kk,
                // 'users.duration_p'  =>   $duration_p,	
                // 'users.duration_in' =>   $duration_in,
                // 'exp_date'          =>   $exp_date,
            ]);

            $user_master = DB::connection('mysql2')->table('users_master_code')->where('username', $request->number)->first();
            DB::connection('mysql2')->table('mastercode_devices')->where('mastercode_devices.user_id', $user_master->id)->update([
                'mastercode_devices.mac' => $request['mac']
            ]);


        } else {

            if (!$request->is_trial) {
                if ($user_type != 'Admin') {
                    $sld->update([

                        'solde' => $sld->solde - 1,

                    ]);
                }
            }
        }


        if ($request->check === null) {
            MasterCode::whereId($id)->update([
                'len' => $request['len'],
                'name' => $request['name'],
                'number' => $request['number'],
                'mac' => $request['mac'],
                'package_id' => $request['pack'],
                'user_id' => $user_id,
                'notes' => $request['notes'] ? $request['notes'] : 'iActive',

            ]);

            $up = DB::table('master_codes')->where('id', $id)->first();


            if ($user_type != 'Admin') {

                if ($request->notes !== null) {
                    $kk = $request->notes;
                    $var = '';
                    //  $kk=1;
                } else {
                    // $kk=0;
                    $kk = 'iActive';
                    $var = '';
                }
            } else {

                if ($request->notes !== null) {
                    $var = $request->notes;
                    $kk = '';
                    // $var=1;
                } else {
                    // $var=0;
                    $var = 'iActive';
                    $kk = '';
                }

            }

            DB::connection('mysql2')->table('users_master_code')->where('users_master_code.username', $request['number'])->update([
                // 'users_master_code.macadress'  => $up->mac,
                'users_master_code.package_id' => $request['pack'],
                'users_master_code.username' => $request['number'],
                'users_master_code.admin_notes' => $var,
                'users_master_code.reseller_notes' => $kk,
                // 'users.duration_p'  =>   $duration_p,	
                // 'users.duration_in' =>   $duration_in,
                // 'exp_date'          =>   $exp_date,
            ]);
            $user_master = DB::connection('mysql2')->table('users_master_code')->where('username', $request->number)->first();
            // $user_master = DB::connection('mysql2')->table('users')->where('users.username' , $request->number)->first();
            DB::connection('mysql2')->table('mastercode_devices')->where('mastercode_devices.user_id', $user_master->id)->update([
                'mastercode_devices.mac' => $request['mac']
            ]);
        }

    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\MasterCode  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, $mac)
    {

        if (Auth::user()->type != "Admin") {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MasterCode::whereIn('user_id', $res)->where('id', $id)->first();
            if ($is_owner) {
            } else {
                return response(['message' => 'Wrong user'], 403);
            }
        }

        $current = DB::table('master_codes')->where('number', $id)->first();
        // dd($current);

        DB::table('master_codes')->where('number', $id)->update([
            'enabled' => 0,
        ]);

        $userT = DB::connection('mysql2')->table('users_master_code')->where('username', $current->number)->first();

        return DB::connection('mysql2')->table('users_master_code')->where('id', $userT->id)->update([
            'enabled' => 0,
        ]);

    }

    public function deleteMastercode($id, $mac, $type)
    {

        if (Auth::user()->type != "Admin") {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MasterCode::whereIn('user_id', $res)->where('id', $id)->first();
            if ($is_owner) {
            } else {
                return response(['message' => 'Wrong user'], 403);
            }
        }

        $current = DB::table('master_codes')->where('number', $id)->first();
        // dd($current);
        if ($type == 'disabled') {
            DB::table('master_codes')->where('number', $id)->update([
                'enabled' => 0,
            ]);

            $userT = DB::connection('mysql2')->table('users_master_code')->where('username', $current->number)->first();

            return DB::connection('mysql2')->table('users_master_code')->where('id', $userT->id)->update([
                'enabled' => 0,
            ]);
        } else {
            DB::table('master_codes')->where('number', $id)->delete();

            $userT = DB::connection('mysql2')->table('users_master_code')->where('username', $current->number)->first();

            return DB::connection('mysql2')->table('users_master_code')->where('id', $userT->id)->delete();
            return DB::connection('mysql2')->table('mastercode_devices')->where('user_id', $userT->id)->delete();
        }

    }

    public function enableMastercode($id, $mac)
    {

        if (Auth::user()->type != "Admin") {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MasterCode::whereIn('user_id', $res)->where('number', $id)->first();
            if ($is_owner) {
            } else {
                return response(['message' => 'Wrong user'], 403);
            }
        }

        $current = DB::table('master_codes')->where('number', $id)->where('mac', $mac)->first();
        // dd($current);

        DB::table('master_codes')->where('number', $id)->where('mac', $mac)->update([
            'enabled' => 1,
        ]);

        $userT = DB::connection('mysql2')->table('users')->where('username', $current->number)->where('macadress', $current->mac)->first();

        return DB::connection('mysql2')->table('users')->where('id', $userT->id)->update([
            'enabled' => 1,
        ]);

    }

    public function Renew(Request $request, $code)
    {

        if (Auth::user()->type != "Admin") {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MasterCode::whereIn('user_id', $res)->where('number', $code)->first();
            if ($is_owner) {
            } else {
                return response(['message' => 'Wrong user'], 403);
            }
        }

        // dd($code);

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $sld = User::find($user_id);

        $user_master = DB::connection('mysql2')->table('users_master_code')->where('users_master_code.username', $code)->first();
        if ($user_type != 'Admin') {
            $count = DB::connection('mysql2')->table('mastercode_devices')->where('mastercode_devices.user_id', $user_master->id)->count();
            if ($sld->solde - $count < 0) {
                DB::rollback();
                return response()->json(['msg' => 'solde'], 401);
            }
        }


        $expiredate = strtotime("+12 month");
        $string_expiredate = date("Y-m-d", $expiredate);

        $current = DB::table('master_codes')->where('number', $code)->first();
        $old = $current->time;
        $ee = new Carbon($old);
        if ($ee > Carbon::now()) {
        } else {
            $old = Carbon::now();
        }
        $date = new Carbon($old);
        $expire = $date->addDays(365);
        $exp = $expire->format('Y-m-d H:i:s');


        $now = Carbon::now();
        $length = $now->diff($exp)->days;



        DB::table('master_codes')->where('number', $code)->update([
            'days' => $length . ' ' . "days",
            'time' => $exp
        ]);

        DB::connection('mysql2')->table('users_master_code')->where('users_master_code.username', $code)->update(
            [
                // 'users_master_code.duration_p'  =>   $length,	
                // 'users_master_code.duration_in' =>   'days',
                'users_master_code.exp_date' => strtotime($exp),
                'is_trial' => 0,
                'is_mag' => 0
            ]
        );

        DB::connection('mysql2')->table('mastercode_devices')->where('mastercode_devices.user_id', $user_master->id)->update(
            [
                // 'users.duration_p'  =>   $length,	
                // 'users.duration_in' =>   'days',
                'mastercode_devices.exp_date_master' => strtotime($exp),
            ]
        );

        $count = DB::connection('mysql2')->table('mastercode_devices')->where('mastercode_devices.user_id', $user_master->id)->count();


        if ($user_type != 'Admin') {
            $sld->update([
                'solde' => $sld->solde - $count
            ]);
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

            $is_owner = MasterCode::whereIn('user_id', $res)->where('id', $id)->first();
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
        // $xtream = DB::connection('mysql2')->table('users')->where('users.username' , $sld->number)->first();

        // dd($code);
        $current = DB::table('master_codes')->find($id);
        $old = $current->time;

        $new_date = explode(" ", $request->days);

        $date = Carbon::now();
        $expire = $date->addDays($new_date[0]);
        $exp = $expire->format('Y-m-d H:i:s');

        $now = Carbon::now();
        $length = $now->diff($exp)->days + 1;

        DB::table('master_codes')->whereId($id)->update([
            'days' => $length . ' ' . "days",
            'time' => $exp,
        ]);


        DB::connection('mysql2')->table('users_master_code')->where('users_master_code.username', $current->number)->update(
            [
                'users_master_code.exp_date' => strtotime($exp),
                'is_mag' => 0
            ]

        );
        $user_master = DB::connection('mysql2')->table('users_master_code')->where('users_master_code.username', $current->number)->first();
        DB::connection('mysql2')->table('mastercode_devices')->where('mastercode_devices.user_id', $user_master->id)->update(
            [
                'mastercode_devices.exp_date_master' => strtotime($exp),
            ]
        );
    }

}
