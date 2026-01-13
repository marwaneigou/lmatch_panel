<?php

namespace App\Http\Controllers;

use App\ActiveCode;
use App\MultiCode;
use App\User;
use App\ResellerStatistic;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\SubResiler;
use App\Coupons;
use GuzzleHttp\Client;
use DB;


class ActiveCodeController extends Controller
{

    public function __construct()
    {
        if(\Request::route()->parameters ) {
            if(isset(\Request::route()->parameters['path']) && \Request::route()->parameters['path'] != 'active') {
                $this->middleware('auth');
            }            
        }
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        if($user_type !== 'Admin'){
                $model = new ActiveCode;
                $ActiveCode =  $model;

                $subRes = SubResiler::select('user_id')->where('res_id', $user_id)->get();

                $subArray = [];

                foreach ($subRes as  $row) {
                    array_push($subArray, $row->user_id);
                }

                array_push($subArray, $user_id);

                $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->select('active_codes.*');
                $ActiveCode = $ActiveCode->whereIn('user_id', $subArray);
                $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
                if($query) $ActiveCode = $ActiveCode->whereIn('active_codes.user_id', $subArray)->
                where(function ($q) use ($query) {
                    return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
                });
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);            
        }else {
                $model = new ActiveCode;
                $ActiveCode =  $model;

                $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->with('user')->select('active_codes.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
                if($query) $ActiveCode = $ActiveCode->where('active_codes.number','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.mac','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.name','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.notes','LIKE', "%{$query}%");
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        }


        
        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;
            $active->latency = 0;

            $active->user = User::select('name')->find($active->user_id);
                    

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->select('is_trial', 'exp_date', 'macadress', 'id')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_activecode')->select('bouquet')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->select('bouquet')->where('username', $active->number)->first();
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;            

            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                $active->exist = $userT->exp_date;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                            $new_days = date_diff($date_today,$exp_time);
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $active->days = $new_days->format("%a days");
                        }else{
                            $active->days = "0 days";
                        }
                    }
                } 
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->select('date_start', 'geoip_country_code', 'user_ip', 'stream_id', 'divergence', 'user_id')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->select('date_start', 'geoip_country_code', 'user_ip', 'stream_id', 'divergence', 'user_id')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                                $date2=date_create( date("Y-m-d H:i:s") );
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->select('stream_display_name')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }else{
                $check_trial = DB::connection('mysql2')->table('packages')->select('is_trial')->where('id', $active->package_id)->first();    
                $active->is_trial = $check_trial->is_trial;
            }
        }
        return Response()->json($ActiveCode);



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
    // public function store(Request $request) {
    //     request()->validate([
    //         'number' => 'required|unique:active_codes',
    //         'name' => 'required',
    //         'len' => 'required',
    //         'pack' => 'required',
    //     ],
    //     [    
    //         'number.required'   => 'please Random Active Codes',
    //         'name.required'   => 'please put your name',
    //         'len.required'   => 'please choose length of code',
    //         'pack.required'   => 'please Choose Package',
    //     ]);

    //     DB::beginTransaction();      
    //     try {
    //         $user = Auth::user();
    //         $user_id = auth()->id();
    //         $user_type = Auth::user()->type;

    //         $pack = DB::connection('mysql2')->table('packages')->find($request->pack);
            
    //         $now = date("Y-m-d H:i:s");
    //         $now = strtotime( "$now" );
    //         $created_at = $now;

    //         // if($request->is_trial){
    //         //     $duration_p = $request->trial_duration;
    //         //     $duration_in = $request->trial_duration_in;
    //         // }else {
    //         //     $duration_p =$request->duration;
    //         //     $duration_in = $request->duration_in;
    //         // }

    //         if($pack->is_trial == "1"){
    //             $duration_p = $pack->trial_duration;
    //             $duration_in = $pack->trial_duration_in;
    //         }else {
    //             $duration_p =$pack->official_duration;
    //             $duration_in = $pack->official_duration_in;
    //         }

    //         // if($duration_p == '1' && $duration_in =='months'){
    //         //     $duration_p = '30' ;
    //         //     $duration_in = 'days';
    //         // }else if($duration_p == '1' && $duration_in =='years'){
    //         //     $duration_p = '365' ;
    //         //     $duration_in = 'days';
    //         // }
            
    //         if($duration_p == '1' && $duration_in =='years'){
    //             $duration_p = '365' ;
    //             $duration_in = 'days';
    //         }else if($duration_p == '1' && $duration_in =='months'){
    //             $duration_p = '30' ;
    //             $duration_in = 'days';
    //         }else if($duration_p == '3' && $duration_in =='months'){
    //             $duration_p = '90' ;
    //             $duration_in = 'days';
    //         }else if($duration_p == '6' && $duration_in =='months'){
    //             $duration_p = '180' ;
    //             $duration_in = 'days';
    //         }else if($duration_p == '24' && $duration_in =='hours'){
    //             $duration_p = '1' ;
    //             $duration_in = 'days';
    //         }
    //         else if($duration_p == '10' && $duration_in =='days'){
    //             $duration_p = '10' ;
    //             $duration_in = 'days';
    //         }
    //         else if($duration_p == '2' && $duration_in =='months'){
    //             $duration_p = '60' ;
    //             $duration_in = 'days';
    //         }else if($duration_p == '366' && $duration_in =='days'){
    //             $duration_p = '366' ;
    //             $duration_in = 'days';
    //         }

    //         $i = $duration_p;
    //         $date   = Carbon::now();
    //         $expire = $date->addDays($i);
    //         $exp = $expire->format('Y-m-d H:i:s');

    //         $sld = User::find($user_id);

    //         if($user_type !='Admin'){
    //             $ss = 1;
    //             if(($duration_p == 30 && $duration_in =='days') || ($duration_p == '1' && $duration_in =='months')) {
    //                 $ss = 0.1;
    //             }else if(($duration_p == 90 && $duration_in =='days') || ($duration_p == '3' && $duration_in =='months')) {
    //                 $ss =0.3;
    //             }else if(($duration_p == 180 && $duration_in =='days') || ($duration_p == '6' && $duration_in =='months')) {
    //                 $ss =0.60;
    //             }
                
    //             if($pack->is_trial == 0){
    //                 if($sld->solde - $ss < 0) {
    //                     DB::rollback();
    //                     return response()->json(['msg'=> 'solde'], 401);
    //                 }
    //             }else{
    //                 if($sld->solde_test - $ss < 0) {
    //                     DB::rollback();
    //                     return response()->json(['msg'=> 'solde'], 401);
    //                 }
    //             }
    //         }

    //         ActiveCode::create([
    //             'len'               => $request['len'],
    //             'name'              => $request['name'],
    //             'number'            => $request['number'],
    //             'days'              => $duration_p . ' ' .$duration_in,
    //             'user_id'           => $user_id,
    //             'notes'             => $request['notes']? $request['notes'] : 'iActive',
    //             'package_id'        => $request['pack'],
    //             'pack'        => json_encode($request['pack_list']),
    //         ]);

    //         $code = '';
    //         if($pack->is_trial == 0){
    //             $post_data = [
    //                 'count' => 1,
    //                 'owner_id' => Auth::user()->id,
    //             ];
    //             $headers = [
    //                 'User-Agent' => 'arcapi',
    //             ];
    //             $client = new Client();
    //             $response = $client->post('https://arcplayer.com/api/free_coupon', [
    //                 'json' => $post_data,
    //                 'headers' => $headers
    //             ]);
    //             $responseData = json_decode($response->getBody(), true);
    //             $code = $responseData['codes'][0];
    //         }

    //         if($user_type != 'Admin'){            
    //             if($request->notes !== null){
    //                 $kk = $request->notes;
    //                 $var = '';
    //             }else {
    //                 $kk = 'iActive';
    //                 $var = '';
    //             }
    //         }else {
                
    //             if($request->notes !== null){
    //                 $var = $request->notes;
    //                 $kk ='';
    //             }else {
    //                 $var = 'iActive';
    //                 $kk ='';
    //             }
                
    //         }
    //         DB::connection('mysql2')->table('users_activecode')->insert(
    //         [
    //             'member_id'   =>   $user_id,
    //             'created_by'  =>   $user_id,
    //             'username'    =>   $request->number,
    //             'password'    =>   $request->password,
    //             'admin_notes' =>    $var,
    //             'reseller_notes' => $kk,
    //             'package_id'  =>   $request->pack,
    //             'duration_p'  =>   $duration_p, 
    //             'duration_in' =>   $duration_in,
    //             'bouquet'     =>   $request->bouquets,
    //             'is_trial'    =>   $pack->is_trial,
    //             'allowed_ips' =>   '',
    //             'allowed_ua'  =>   '',
    //             'created_at'  =>   $created_at,
    //             'typecode'    =>   2,
    //             'exp_date'    => 0,
    //             'forced_country' => "",
    //             // "access_output_id" => "[1,2,3]",
    //             'play_token'=>"",
    //             'output'    =>'["m3u8","ts","rtmp"]'

    //         ]);
                

    //         $id = DB::connection('mysql2')->table('users')->orderBy('users.id', 'desc')->first()->id;            
    //         // DB::connection('mysql2')->table('user_output')->insert(
    //         // [
    //         //     'user_id'   =>     $id,
    //         //     'access_output_id'  =>   1,
                
    //         // ]);                
    //         // DB::connection('mysql2')->table('user_output')->insert(
    //         // [
    //         //     'user_id'   =>     $id,
    //         //     'access_output_id'  =>   2,
                
    //         // ]);

    //         if($user_type !='Admin'){
    //             $ss = 1;
    //             if(($duration_p == 30 && $duration_in =='days') || ($duration_p == '1' && $duration_in =='months')) {
    //                 $ss = 0.1;
    //             }else if(($duration_p == 90 && $duration_in =='days') || ($duration_p == '3' && $duration_in =='months')) {
    //                 $ss =0.3;
    //             }else if(($duration_p == 180 && $duration_in =='days') || ($duration_p == '6' && $duration_in =='months')) {
    //                 $ss =0.60;
    //             }
                
    //             if($pack->is_trial == 0){
    //                 if($sld->solde - $ss < 0) {
    //                     DB::rollback();
    //                     return response()->json(['msg'=> 'solde'], 401);
    //                 }
    //                 $sld->update([
                            
    //                     'solde' => $sld->solde - $ss

    //                 ]);

    //                 ResellerStatistic::create([
    //                     'reseller_id' => $user_id,
    //                     'solde' => $ss,
    //                     'operation' => 0,
    //                     'operation_name' => 'active_code',
    //                     'slug' => 'create'
    //                 ]);
    //             }else{
    //                 if($sld->solde_test - $ss < 0) {
    //                     DB::rollback();
    //                     return response()->json(['msg'=> 'solde'], 401);
    //                 }
    //                 $sld->update([
                            
    //                     'solde_test' => $sld->solde_test - $ss

    //                 ]);
    //             }
    //         }
    //         DB::commit();
    //         return response()->json(['code'=>  $code], 200);
           
    //     } catch (\Throwable $th) {
    //         DB::rollback();
    //         abort(401);
    //     }

    // }

    public function store(Request $request) {
        request()->validate([
            'number' => 'required|unique:active_codes',
            'name' => 'required',
            'len' => 'required',
            'pack' => 'required',
        ],
        [    
            'number.required'   => 'please Random Active Codes',
            'name.required'   => 'please put your name',
            'len.required'   => 'please choose length of code',
            'pack.required'   => 'please Choose Package',
        ]);

        DB::beginTransaction();      
        try {
            $user = Auth::user();
            $user_id = auth()->id();
            $user_type = Auth::user()->type;

            $pack = DB::connection('mysql2')->table('packages')->find($request->pack);
            
            $now = date("Y-m-d H:i:s");
            $now = strtotime( "$now" );
            $created_at = $now;

            // if($request->is_trial){
            //     $duration_p = $request->trial_duration;
            //     $duration_in = $request->trial_duration_in;
            // }else {
            //     $duration_p =$request->duration;
            //     $duration_in = $request->duration_in;
            // }

            if($pack->is_trial == "1"){
                $duration_p = $pack->trial_duration;
                $duration_in = $pack->trial_duration_in;
            }else {
                $duration_p =$pack->official_duration;
                $duration_in = $pack->official_duration_in;
            }

            // if($duration_p == '1' && $duration_in =='months'){
            //     $duration_p = '30' ;
            //     $duration_in = 'days';
            // }else if($duration_p == '1' && $duration_in =='years'){
            //     $duration_p = '365' ;
            //     $duration_in = 'days';
            // }
            
            if($duration_p == '1' && $duration_in =='years'){
                $duration_p = '365' ;
                $duration_in = 'days';
            }else if($duration_p == '1' && $duration_in =='months'){
                $duration_p = '30' ;
                $duration_in = 'days';
            }else if($duration_p == '3' && $duration_in =='months'){
                $duration_p = '90' ;
                $duration_in = 'days';
            }else if($duration_p == '6' && $duration_in =='months'){
                $duration_p = '180' ;
                $duration_in = 'days';
            }else if($duration_p == '24' && $duration_in =='hours'){
                $duration_p = '1' ;
                $duration_in = 'days';
            }
            else if($duration_p == '10' && $duration_in =='days'){
                $duration_p = '10' ;
                $duration_in = 'days';
            }
            else if($duration_p == '2' && $duration_in =='months'){
                $duration_p = '60' ;
                $duration_in = 'days';
            }else if($duration_p == '366' && $duration_in =='days'){
                $duration_p = '366' ;
                $duration_in = 'days';
            }

            $i = $duration_p;
            $date   = Carbon::now();
            $expire = $date->addDays($i);
            $exp = $expire->format('Y-m-d H:i:s');

            $sld = User::find($user_id);

            if($user_type !='Admin'){
                $ss = 1;
                if(($duration_p == 30 && $duration_in =='days') || ($duration_p == '1' && $duration_in =='months')) {
                    $ss = 0.1;
                }else if(($duration_p == 90 && $duration_in =='days') || ($duration_p == '3' && $duration_in =='months')) {
                    $ss =0.3;
                }else if(($duration_p == 180 && $duration_in =='days') || ($duration_p == '6' && $duration_in =='months')) {
                    $ss =0.60;
                }
                
                if($pack->is_trial == 0){
                    if($sld->solde - $ss < 0) {
                        DB::rollback();
                        return response()->json(['msg'=> 'solde'], 401);
                    }
                }else{
                    if($sld->solde_test - $ss < 0) {
                        DB::rollback();
                        return response()->json(['msg'=> 'solde'], 401);
                    }
                }
            }

            ActiveCode::create([
                'len'               => $request['len'],
                'name'              => $request['name'],
                'number'            => $request['number'],
                'days'              => $duration_p . ' ' .$duration_in,
                'user_id'           => $user_id,
                'notes'             => $request['notes']? $request['notes'] : 'iActive',
                'package_id'        => $request['pack'],
                'pack'        => json_encode($request['pack_list']),
            ]);

            $code = '';
            // if($pack->is_trial == 0){
            //     $post_data = [
            //         'count' => 1,
            //         'owner_id' => Auth::user()->id,
            //     ];
            //     $headers = [
            //         'User-Agent' => 'arcapi',
            //     ];
            //     $client = new Client();
            //     $response = $client->post('https://arcplayer.com/api/free_coupon', [
            //         'json' => $post_data,
            //         'headers' => $headers
            //     ]);
            //     $responseData = json_decode($response->getBody(), true);
            //     $code = $responseData['codes'][0];
            // }

            if($user_type != 'Admin'){            
                if($request->notes !== null){
                    $kk = $request->notes;
                    $var = '';
                }else {
                    $kk = 'iActive';
                    $var = '';
                }
            }else {
                
                if($request->notes !== null){
                    $var = $request->notes;
                    $kk ='';
                }else {
                    $var = 'iActive';
                    $kk ='';
                }
                
            }
            DB::connection('mysql2')->table('users_activecode')->insert(
            [
                'member_id'   =>   $user_id,
                'created_by'  =>   $user_id,
                'username'    =>   $request->number,
                'password'    =>   $request->password,
                'admin_notes' =>    $var,
                'reseller_notes' => $kk,
                'package_id'  =>   $request->pack,
                'duration_p'  =>   $duration_p, 
                'duration_in' =>   $duration_in,
                'bouquet'     =>   $request->bouquets,
                'is_trial'    =>   $pack->is_trial,
                'allowed_ips' =>   '',
                'allowed_ua'  =>   '',
                'created_at'  =>   $created_at,
                'typecode'    =>   2,
                'exp_date'    => 0,
                'forced_country' => "",
                // "access_output_id" => "[1,2,3]",
                'play_token'=>"",
                'output'    =>'["m3u8","ts","rtmp"]'

            ]);
                

            $id = DB::connection('mysql2')->table('users')->orderBy('users.id', 'desc')->first()->id;            
            // DB::connection('mysql2')->table('user_output')->insert(
            // [
            //     'user_id'   =>     $id,
            //     'access_output_id'  =>   1,
                
            // ]);                
            // DB::connection('mysql2')->table('user_output')->insert(
            // [
            //     'user_id'   =>     $id,
            //     'access_output_id'  =>   2,
                
            // ]);

            if($user_type !='Admin'){
                $ss = 1;
                if(($duration_p == 30 && $duration_in =='days') || ($duration_p == '1' && $duration_in =='months')) {
                    $ss = 0.1;
                }else if(($duration_p == 90 && $duration_in =='days') || ($duration_p == '3' && $duration_in =='months')) {
                    $ss =0.3;
                }else if(($duration_p == 180 && $duration_in =='days') || ($duration_p == '6' && $duration_in =='months')) {
                    $ss =0.60;
                }
                
                if($pack->is_trial == 0){
                    if($sld->solde - $ss < 0) {
                        DB::rollback();
                        return response()->json(['msg'=> 'solde'], 401);
                    }
                    $sld->update([
                            
                        'solde' => $sld->solde - $ss

                    ]);

                    ResellerStatistic::create([
                        'reseller_id' => $user_id,
                        'solde' => $ss,
                        'operation' => 0,
                        'operation_name' => 'active_code',
                        'slug' => 'create'
                    ]);
                }else{
                    if($sld->solde_test - $ss < 0) {
                        DB::rollback();
                        return response()->json(['msg'=> 'solde'], 401);
                    }
                    $sld->update([
                            
                        'solde_test' => $sld->solde_test - $ss

                    ]);
                }
            }
            DB::commit();
            return response()->json(['code'=>  $code], 200);
           
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
    public function update(Request $request, $id)
    {

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = ActiveCode::whereIn('user_id', $res)->where('id', $id)->first();
            if($is_owner) {}
            else{
                return response(['message'=>'Wrong user'], 403);
            }
        }

        request()->validate([
            'number' => 'required|unique:active_codes,id',
            'name' => 'required',
        ]);
     
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $current = DB::table('active_codes')->where('id', $id)->first();
        $xtream = DB::connection('mysql2')->table('users_activecode')->where('username' , $current->number)->first();
        if($xtream) {}
        else{
            $xtream = DB::connection('mysql2')->table('users')->where('username' , $current->number)->first();
        }
        
        $sld = User::find($user_id);

       
        $pack = DB::connection('mysql2')->table('packages')->find($request->pack);
        if($request->pack != $current->package_id){

            // if($request->is_trial){
            //     $duration_p = $request->trial_duration;
            //     $duration_in = $request->trial_duration_in;
            // }else {
            //     $duration_p =$request->duration;
            //     $duration_in = $request->duration_in;
            // }

            if($pack->is_trial == "1"){
                $duration_p = $pack->trial_duration;
                $duration_in = $pack->trial_duration_in;
            }else {
                $duration_p =$pack->official_duration;
                $duration_in = $pack->official_duration_in;
            }
          
            if($duration_p == '1' && $duration_in =='years'){
                $duration_p = '365' ;
                $duration_in = 'days';
            }else if($duration_p == '1' && $duration_in =='months'){
                $duration_p = '30' ;
                $duration_in = 'days';

            }else if($duration_p == '3' && $duration_in =='months'){
                $duration_p = '90' ;
                $duration_in = 'days';
            }else if($duration_p == '6' && $duration_in =='months'){
                $duration_p = '180' ;
                $duration_in = 'days';
            }else if($duration_p == '24' && $duration_in =='hours'){
                $duration_p = '1' ;
                $duration_in = 'days';
            }
            else if($duration_p == '10' && $duration_in =='days'){
                $duration_p = '10' ;
                $duration_in = 'days';
            }
            else if($duration_p == '2' && $duration_in =='months'){
                $duration_p = '60' ;
                $duration_in = 'days';
            }


            $days = $duration_p . ' ' .$duration_in;

            $time = Null;

            $exp_date =  Null;
            $mac = '';
            

        }else {
            $days = $current->days;
            $time = $current->time;

            $duration_p = $xtream->duration_p;
            $duration_in = $xtream->duration_in;
            // $mac = $xtream->macadress;
            $exp_date =  $xtream->exp_date;

        }

        ActiveCode::whereId($id)->update([
            'len'               => $request['len'],
            'name'              => $request['name'],
            'number'            => $request['number'] ,
            // 'mac'               => $mac,
            'package_id'        => $request['pack'],
            // 'days'              => $days,
            'notes'             => $request['notes']? $request['notes'] : 'iActive',
            'pack'        => json_encode($request['pack_list']),
        ]);
        
        if($user_type != 'Admin'){               
            if($request->notes !== null){
                $kk = $request->notes;
                $var = '';
            }else {
                $kk = 'iActive';
                $var = '';
            }
        }else {                
            if($request->notes !== null){
                $var = $request->notes;
                $kk ='';
            }else {
                $var = 'iActive';
                $kk ='';
            }
            
        }
        
        $check_usact = DB::connection('mysql2')->table('users')->where('username' , $current->number)->first();
        if($check_usact) {
            DB::connection('mysql2')->table('users')->where('username' , $current->number)->update(
                [
                    'package_id' => $request['pack'],
                    'username'   => $request['number'],
                    'admin_notes' =>    $var,
                    'reseller_notes' => $kk,
                    // 'duration_p'  =>   $duration_p,	
                    // 'duration_in' =>   $duration_in, 
                    'bouquet'     =>   $request->bouquets,
                    'is_trial'    =>   $pack->is_trial,
                    // 'exp_date'    =>   $exp_date,
                    // 'macadress'   =>   $mac,

                ]

            );            
        }else {           
            DB::connection('mysql2')->table('users_activecode')->where('username' , $current->number)->update(
                [
                    'package_id' => $request['pack'],
                    'username'   => $request['number'],
                    'admin_notes' =>    $var,
                    'reseller_notes' => $kk,
                    // 'duration_p'  =>   $duration_p,	
                    // 'duration_in' =>   $duration_in, 
                    'bouquet'     =>   $request->bouquets,
                    'is_trial'    =>   $pack->is_trial,
                    // 'exp_date'    =>   $exp_date,
                    'macadress'   =>   $mac,
                    'output'    =>'["m3u8","ts","rtmp"]'

                ]

            );
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\ActiveCode  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, $type)
    {

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = ActiveCode::whereIn('user_id', $res)->where('id', $id)->first();
            if($is_owner) {}
            else{
                return response(['message'=>'Wrong user'], 403);
            }
        }

        DB::beginTransaction();
        try {
            $user = Auth::user();
            $user_id = auth()->id();
            $user_type = Auth::user()->type;

            $current = DB::table('active_codes')->where('id', $id)->first();

            if($type == 'disabled'){

                $ActiveCode = ActiveCode::findOrFail($id);
                ActiveCode::whereId($id)->update([
                    'enabled' => 0 ,
                    
                ]);
                $get_user =  DB::connection('mysql2')->table('users')->where('username' , $current->number)->first();
                if($get_user) {
                    DB::commit();
                    return DB::connection('mysql2')->table('users')->where('username' , $current->number)->update(
                        [
                            'enabled' => 0,
                        ]
                    );
                }else{
                    DB::commit();
                    return DB::connection('mysql2')->table('users_activecode')->where('username' , $current->number)->update(
                        [
                            'enabled' => 0,
                        ]
                    );
                }

            }else{
                $ActiveCode = ActiveCode::findOrFail($id)->delete();
                if($type == 'transfer') {
                    $get_user =  DB::connection('mysql2')->table('users')->where('username' , $current->number)->first();
                    DB::commit();
                    return response()->json(['login' => $get_user->username, 'password' => $get_user->password]);
                }else{
                    $get_user =  DB::connection('mysql2')->table('users')->where('username' , $current->number)->delete();
                }
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(["error" => "error"], 500);
        } 
        
    }

    public function resetMac($id){

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = ActiveCode::whereIn('user_id', $res)->where('id', $id)->first();
            if($is_owner) {}
            else{
                return response(['message'=>'Wrong user'], 403);
            }
        }

        $current = DB::table('active_codes')->where('id', $id)->first();

        ActiveCode::whereId($id)->update(['mac' => '']);

        DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->update(['users.macadress' => 'Mac Reseted']);
    }


    public function showM3U(Request $request)
    {

        if(Auth::check()) {
            if(Auth::user()->type != "Admin") {
                $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
                array_push($res, Auth::user()->id);
                
                $is_owner = ActiveCode::whereIn('user_id', $res)->where('number', $request->code)->first();
                $is_owner2 = MultiCode::whereIn('user_id', $res)->where('number', $request->code)->first();
                if($is_owner || $is_owner2) {}
                else{
                    return response(['message'=>'Wrong user'], 403);
                }
            }
        }
       
        
        $code = $request->code;
        // dd($id);
        $info = DB::connection('mysql2')->table('users')->where('users.username' , $code)->first();

        $site = "http://atrupo4k.com:80/get.php?username=";

        $ActiveCode = ActiveCode::where('number', $request->code)->first();
        if($ActiveCode) {
            // $get_user = User::find($ActiveCode->user_id);
            if(Auth::check()) {
                $get_user = User::find(Auth::user()->id);
                if($get_user) {
                    if($get_user->host != null) {
                        $site = "http://" . $get_user->host . ':80/get.php?username=';
                    }
                }
            }            
        }else{
            $MultiCode = MultiCode::where('number', $request->code)->first();
            if($MultiCode) {
                // $get_user = User::find($MultiCode->user_id);
                if(Auth::check()) {
                    $get_user = User::find(Auth::user()->id);
                    if($get_user) {
                        if($get_user->host != null) {
                            $site = "http://" . $get_user->host . ':80/get.php?username=';
                        }
                    }
                }
            }
        }
        
        $user = $info->username;
        $pass = $info->password;

        $m3u =  $site.$user."&password=".$pass;

        return Response()->json($m3u);


    }   


    public function Renew(Request $request, $code)
    {

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = ActiveCode::whereIn('user_id', $res)->where('number', $code)->first();
            if($is_owner) {}
            else{
                return response(['message'=>'Wrong user'], 403);
            }
        }

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $user_pack = Auth::user()->package_id;
        $oo = "[$user_pack]";
        $yy = json_decode('[' . $oo . ']', true);

        // if($user_type != 'Admin'){
            
        //     $dd = DB::connection('mysql2')->table('packages')->select('packages.*')->whereIn('packages.id' , $yy[0])->get();
            
        // }else {
        //     $dd = DB::connection('mysql2')->table('packages')->select('packages.*')->get();
        // }
        // $pack_id = $request->package_id;
        // $getP = DB::connection('mysql2')->table('packages')->select('packages.*')
        //     ->where('packages.id' , $pack_id)->first();
        // if($getP->is_trial == 1) {
        //     foreach ($dd as $pack) {
        //         if($pack->official_duration == '365' || $pack->official_duration == '366') {
        //             $pack_id = $pack->id;
        //         }
        //     }
        // }        

        $pack= $request->package_id;

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $user_solde = Auth::user()->solde;
        $sld = User::find($user_id);

        
        $ss = 1;
        $days = 365;
        if(intval($request->month) == 30) {
            $ss = 0.1;
            $days = 30;
        }else if(intval($request->month) == 90) {
            $ss =0.3;
            $days = 90;
        }else if(intval($request->month) == 180) {
            $ss =0.60;
            $days = 180;
        }
    
        if($user_type !='Admin'){
            if($sld->solde - $ss < 0) {
                return response()->json(['msg'=> 'solde'], 401);
            }
        }

        $current =  DB::table('active_codes')->where('number', $code)->first();
        $old = $current->time;
        if($current->time == null) {
            $c_user = DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->first();
            if($c_user) {
                $old = Date('Y/m/d H:i:s',  $c_user->exp_date);
            }else{
                $old = Date('Y/m/d H:i:s', strtotime("+".$current->days));
            }            
        }else{
            $old = $current->time;
        }
        // return response()->json(['msg'=> $old, 'user' => $c_user], 500); 
        $ee = new Carbon($old);
        if($ee > Carbon::now()) {}
        else{
            $old = Carbon::now();
        }
        
        $date   = new Carbon($old);
        $expire = $date->addDays(intval($days));
        $exp = $expire->format('Y-m-d H:i:s');

        $now = Carbon::now();
        $length = $now->diff($exp)->days;

        DB::table('active_codes')->where('number', $code)->update([
            'days'  => $length.' '."days",
            'time'  => $exp,
            // 'package_id' => $pack_id,
            
        ]);

        DB::connection('mysql2')->table('users_activecode')->where('users_activecode.username' , $code)->update(
            [
                'duration_p'  =>    $length,	
                'duration_in' =>   'days',
                'is_trial'          => 0,
                // 'package_id' => $pack_id,
                'is_mag' => 0
            ]

        );
            

        DB::connection('mysql2')->table('users')->where('users.username' , $code)->update(
            [
                // 'users.duration_p'  =>    $length,	
                // 'users.duration_in' =>   'days',
                'users.exp_date'    =>   strtotime($exp),
                'is_trial'          => 0,
                // 'package_id' => $pack_id,
                'is_mag' => 0
            ]

        );

            if($user_type !='Admin'){
                $ss = 1;
                if(intval($request->month) == 30) {
                    $ss = 0.1;
                }else if(intval($request->month) == 90) {
                    $ss =0.3;
                }else if(intval($request->month) == 180) {
                    $ss =0.60;
                }
                
                if($sld->solde - $ss < 0) {
                    return response()->json(['msg'=> 'solde'], 401);
                }
                $sld->update([
                        
                    'solde' => $sld->solde - $ss

                ]);

                ResellerStatistic::create([
                    'reseller_id' => $user_id,
                    'solde' => $ss,
                    'operation' => 0,
                    'operation_name' => 'active_code',
                    'slug' => 'renew'
                ]);
                
            }

        

    }


    public function changeDays(Request $request, $id)
    {

        if(Auth::user()->type != "Admin") {
            abort(401);
        }

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);
            $is_owner = ActiveCode::whereIn('user_id', $res)->where('id', $id)->first();
            if($is_owner) {}
            else{
                return response(['message'=>'Wrong user'], 403);
            }
        }

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $user_solde = Auth::user()->solde;


                $sld = User::find($user_id);
                // $xtream = DB::connection('mysql2')->table('users')->where('users.username' , $sld->number)->first();

                // dd($code);
                $current =  DB::table('active_codes')->find($id);
                $old = $current->time; 

                $new_date = explode(" ", $request->days);

                $date   = Carbon::now();
                $expire = $date->addDays($new_date[0]);
                $exp = $expire->format('Y-m-d H:i:s');

                $now = Carbon::now();
                $length = $now->diff($exp)->days +1;

                DB::table('active_codes')->whereId($id)->update([
                    'days'  => $length.' '."days",
                    'time'  =>  $exp,
                ]);
                
                $user_ex = DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->first();
                if($user_ex) {
                    DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->update(
                        [
                            // 'users.duration_p'  =>    $length,	
                            // 'users.duration_in' =>   'days',
                            'users.exp_date'    =>   strtotime($exp),
                            'is_mag' => 0
                        ]
    
                    );
                }else{
                    DB::connection('mysql2')->table('users_activecode')->where('username' , $current->number)->update(
                        [
                            'users_activecode.duration_p'  =>    $length,	
                            'users_activecode.duration_in' =>   'days',
                            'users_activecode.exp_date'    =>   strtotime($exp),
                            'is_mag' => 0
                        ]
    
                    );
                }

                
    }

    public function byReseller(Request $req, $resID) {

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $model = new ActiveCode;
        $ActiveCode =  $model;

        $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
        $ActiveCode = $ActiveCode->select('active_codes.*');
        $ActiveCode = $ActiveCode->where('user_id', $resID);
        $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
        if($query) $ActiveCode = $ActiveCode->where('active_codes.user_id', $resID)->
        where(function ($q) use ($query) {
            return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
        });
        $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        
        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency = 0;

            $active->user = User::find($active->user_id);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($user_activecode) { $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            elseif( $user_users){ $active->selected_bouquets = json_decode($user_users->bouquet); }
            
            if($userT) {
                // if($check_trial){
                    $active->is_trial = $userT->is_trial;
                // }else{
                //     $active->is_trial = 0;
                // }
               
                $active->exist = $userT->exp_date;
                // $active->typecode = $userT->typecode;
                // $active->duration = $userT->duration;
                // $active->day = $userT->day;
                // $active->user_mac = $userT->macadress;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                    }
                } 
                // $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                // $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                $date2=date_create( date("Y-m-d H:i:s") );
                            // else
                                // $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                
                
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }
        }
        // $users_activities = DB::connection('mysql2')->table('xtream_main')->where('root_ip', '41.250.65.114')->get();
        // $users_activities = DB::connection('mysql2')->table('login_logs')->where('login_ip', '41.251.149.186')->get();
        // $users_activities = DB::connection('mysql2')->table('users')->where('username', '190722753591452')->get();
        // $users_activities = DB::connection('mysql2')->table('login_logs')->get();
        return Response()->json($ActiveCode);



    }



    public function expiredItems()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $users = DB::connection('mysql2')->table('users')->where('exp_date', '<', strtotime(date("Y/m/d H:i:s")))->pluck('username');

        if($user_type !== 'Admin'){
                $model = new ActiveCode;
                $ActiveCode =  $model;

                $subRes = SubResiler::where('res_id', $user_id)->get();

                $subArray = [];

                foreach ($subRes as  $row) {
                    array_push($subArray, $row->user_id);
                }

                array_push($subArray, $user_id);

                $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->select('active_codes.*');
                $ActiveCode = $ActiveCode->whereIn('user_id', $subArray);
                $ActiveCode = $ActiveCode->whereIn('number', $users);
                $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
                if($query) $ActiveCode = $ActiveCode->whereIn('active_codes.user_id', $subArray)->
                where(function ($q) use ($query) {
                    return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
                });
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);            
        }else {
                $model = new ActiveCode;
                $ActiveCode =  $model;

                $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->with('user')->select('active_codes.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->whereIn('number', $users);
                if($query) $ActiveCode = $ActiveCode->where('active_codes.number','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.mac','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.name','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.notes','LIKE', "%{$query}%");
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        }
        
        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency = 0;

            $active->user = User::find($active->user_id);
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                $active->exist = $userT->exp_date;
                // $active->typecode = $userT->typecode;
                // $active->duration = $userT->duration;
                // $active->day = $userT->day;
                // $active->user_mac = $userT->macadress;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 

                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $active->days = $new_days->format("%a days");
                        }else{
                            $active->days = "0 days";
                        }
                    }
                } 
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                $date2=date_create( date("Y-m-d H:i:s") );
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }
        }
        return Response()->json($ActiveCode);

    }

    public function onlineItems()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $users_activity_now_pluck = DB::connection('mysql2')->table('con_activities')->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->pluck('user_id');
        $users = DB::connection('mysql2')->table('users')->whereIn('id', $users_activity_now_pluck)->pluck('username');

        if($user_type !== 'Admin'){
                $model = new ActiveCode;
                $ActiveCode =  $model;

                $subRes = SubResiler::where('res_id', $user_id)->get();

                $subArray = [];

                foreach ($subRes as  $row) {
                    array_push($subArray, $row->user_id);
                }

                array_push($subArray, $user_id);

                $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->select('active_codes.*');
                $ActiveCode = $ActiveCode->whereIn('user_id', $subArray);
                $ActiveCode = $ActiveCode->whereIn('number', $users);
                $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
                if($query) $ActiveCode = $ActiveCode->whereIn('active_codes.user_id', $subArray)->
                where(function ($q) use ($query) {
                    return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
                });
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);            
        }else {
                $model = new ActiveCode;
                $ActiveCode =  $model;

                $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->with('user')->select('active_codes.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->whereIn('number', $users);
                if($query) $ActiveCode = $ActiveCode->where('active_codes.number','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.mac','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.name','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.notes','LIKE', "%{$query}%");
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        }
        
        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency = 0;

            $active->user = User::find($active->user_id);
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                $active->exist = $userT->exp_date;
                // $active->typecode = $userT->typecode;
                // $active->duration = $userT->duration;
                // $active->day = $userT->day;
                // $active->user_mac = $userT->macadress;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 

                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $active->days = $new_days->format("%a days");
                        }else{
                            $active->days = "0 days";
                        }
                    }
                } 
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                $date2=date_create( date("Y-m-d H:i:s") );
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }
        }
        return Response()->json($ActiveCode);

    }

    public function amolstExpiredItems()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $users = DB::connection('mysql2')->table('users')
            ->where('exp_date', '<=', strtotime(Date('Y/m/d H:i:s', strtotime("+2 days"))))
            ->where('exp_date', '>', strtotime(date("Y/m/d H:i:s")))
            ->pluck('username');

        if($user_type !== 'Admin'){
            $model = new ActiveCode;
            $ActiveCode =  $model;

            $subRes = SubResiler::where('res_id', $user_id)->get();

            $subArray = [];

            foreach ($subRes as  $row) {
                array_push($subArray, $row->user_id);
            }

            array_push($subArray, $user_id);

            $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->select('active_codes.*');
            $ActiveCode = $ActiveCode->whereIn('user_id', $subArray);
            $ActiveCode = $ActiveCode->whereIn('number', $users);
            $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
            if($query) $ActiveCode = $ActiveCode->whereIn('active_codes.user_id', $subArray)->
            where(function ($q) use ($query) {
                return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
            });
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);            
        }else {
            $model = new ActiveCode;
            $ActiveCode =  $model;

            $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->with('user')->select('active_codes.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
            $ActiveCode = $ActiveCode->whereIn('number', $users);
            if($query) $ActiveCode = $ActiveCode->where('active_codes.number','LIKE', "%{$query}%")
                                                            ->orWhere('active_codes.mac','LIKE', "%{$query}%")
                                                            ->orWhere('active_codes.name','LIKE', "%{$query}%")
                                                            ->orWhere('active_codes.notes','LIKE', "%{$query}%");
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }
        
        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency = 0;

            $active->user = User::find($active->user_id);
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                $active->exist = $userT->exp_date;
                // $active->typecode = $userT->typecode;
                // $active->duration = $userT->duration;
                // $active->day = $userT->day;
                // $active->user_mac = $userT->macadress;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 

                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $active->days = $new_days->format("%a days");
                        }else{
                            $active->days = "0 days";
                        }
                    }
                } 
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                $date2=date_create( date("Y-m-d H:i:s") );
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }
        }
        return Response()->json($ActiveCode);

    }

    public function trialItems()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $packages = DB::connection('mysql2')->table('packages')->where('is_trial', 1)->pluck('id');

        if($user_type !== 'Admin'){
                $model = new ActiveCode;
                $ActiveCode =  $model;

                $subRes = SubResiler::where('res_id', $user_id)->get();

                $subArray = [];

                foreach ($subRes as  $row) {
                    array_push($subArray, $row->user_id);
                }

                array_push($subArray, $user_id);

                $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->select('active_codes.*');
                $ActiveCode = $ActiveCode->whereIn('user_id', $subArray);
                $ActiveCode = $ActiveCode->whereIn('active_codes.package_id', $packages);
                $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
                if($query) $ActiveCode = $ActiveCode->whereIn('active_codes.user_id', $subArray)->
                where(function ($q) use ($query) {
                    return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
                });
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);            
        }else {
                $model = new ActiveCode;
                $ActiveCode =  $model;

                $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->with('user')->select('active_codes.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->whereIn('active_codes.package_id', $packages);
                if($query) $ActiveCode = $ActiveCode->where('active_codes.number','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.mac','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.name','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.notes','LIKE', "%{$query}%");
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        }
        
        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency = 0;

            $active->user = User::find($active->user_id);
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                $active->exist = $userT->exp_date;
                // $active->typecode = $userT->typecode;
                // $active->duration = $userT->duration;
                // $active->day = $userT->day;
                // $active->user_mac = $userT->macadress;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 

                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $active->days = $new_days->format("%a days");
                        }else{
                            $active->days = "0 days";
                        }
                    }
                } 
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                $date2=date_create( date("Y-m-d H:i:s") );
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }
        }
        return Response()->json($ActiveCode);

    }

    public function enabledItems()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        if($user_type !== 'Admin'){
                $model = new ActiveCode;
                $ActiveCode =  $model;

                $subRes = SubResiler::where('res_id', $user_id)->get();

                $subArray = [];

                foreach ($subRes as  $row) {
                    array_push($subArray, $row->user_id);
                }

                array_push($subArray, $user_id);

                $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->select('active_codes.*');
                $ActiveCode = $ActiveCode->whereIn('user_id', $subArray);
                $ActiveCode = $ActiveCode->where('enabled', 1);
                $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
                if($query) $ActiveCode = $ActiveCode->whereIn('active_codes.user_id', $subArray)->
                where(function ($q) use ($query) {
                    return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
                });
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);            
        }else {
                $model = new ActiveCode;
                $ActiveCode =  $model;

                $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->with('user')->select('active_codes.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->where('enabled', 1);
                if($query) $ActiveCode = $ActiveCode->where('active_codes.number','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.mac','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.name','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.notes','LIKE', "%{$query}%");
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        }
        
        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency = 0;

            $active->user = User::find($active->user_id);
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                $active->exist = $userT->exp_date;
                // $active->typecode = $userT->typecode;
                // $active->duration = $userT->duration;
                // $active->day = $userT->day;
                // $active->user_mac = $userT->macadress;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 

                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $active->days = $new_days->format("%a days");
                        }else{
                            $active->days = "0 days";
                        }
                    }
                } 
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                $date2=date_create( date("Y-m-d H:i:s") );
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }
        }
        return Response()->json($ActiveCode);

    }

    public function disabledItems()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        if($user_type !== 'Admin'){
                $model = new ActiveCode;
                $ActiveCode =  $model;

                $subRes = SubResiler::where('res_id', $user_id)->get();

                $subArray = [];

                foreach ($subRes as  $row) {
                    array_push($subArray, $row->user_id);
                }

                array_push($subArray, $user_id);

                $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->select('active_codes.*');
                $ActiveCode = $ActiveCode->whereIn('user_id', $subArray);
                $ActiveCode = $ActiveCode->where('enabled', 0);
                $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
                if($query) $ActiveCode = $ActiveCode->whereIn('active_codes.user_id', $subArray)->
                where(function ($q) use ($query) {
                    return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
                });
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);            
        }else {
                $model = new ActiveCode;
                $ActiveCode =  $model;

                $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->with('user')->select('active_codes.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->where('enabled', 0);
                if($query) $ActiveCode = $ActiveCode->where('active_codes.number','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.mac','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.name','LIKE', "%{$query}%")
                                                              ->orWhere('active_codes.notes','LIKE', "%{$query}%");
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        }
        
        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency = 0;

            $active->user = User::find($active->user_id);
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                $active->exist = $userT->exp_date;
                // $active->typecode = $userT->typecode;
                // $active->duration = $userT->duration;
                // $active->day = $userT->day;
                // $active->user_mac = $userT->macadress;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 

                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $active->days = $new_days->format("%a days");
                        }else{
                            $active->days = "0 days";
                        }
                    }
                } 
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                $date2=date_create( date("Y-m-d H:i:s") );
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }
        }
        return Response()->json($ActiveCode);

    }


    public function expiredItemsByUser(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $model = new ActiveCode;
        $ActiveCode =  $model;

        $users = DB::connection('mysql2')->table('users')->where('exp_date', '<', strtotime(date("Y/m/d H:i:s")))->pluck('username');

        $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
        $ActiveCode = $ActiveCode->select('active_codes.*');
        $ActiveCode = $ActiveCode->where('user_id', $resID);
        $ActiveCode = $ActiveCode->whereIn('number', $users);
        $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
        if($query) $ActiveCode = $ActiveCode->where('active_codes.user_id', $resID)->
        where(function ($q) use ($query) {
            return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
        });
        $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        
        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency = 0;

            $active->user = User::find($active->user_id);
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                $active->exist = $userT->exp_date;
                // $active->typecode = $userT->typecode;
                // $active->duration = $userT->duration;
                // $active->day = $userT->day;
                // $active->user_mac = $userT->macadress;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 

                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $active->days = $new_days->format("%a days");
                        }else{
                            $active->days = "0 days";
                        }
                    }
                } 
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                $date2=date_create( date("Y-m-d H:i:s") );
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }
        }
        return Response()->json($ActiveCode);

    }

    public function onlineItemsByUser(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $model = new ActiveCode;
        $ActiveCode =  $model;

        $users_activity_now_pluck = DB::connection('mysql2')->table('con_activities')->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->pluck('user_id');
        $users = DB::connection('mysql2')->table('users')->whereIn('id', $users_activity_now_pluck)->pluck('username');

        $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
        $ActiveCode = $ActiveCode->select('active_codes.*');
        $ActiveCode = $ActiveCode->where('user_id', $resID);
        $ActiveCode = $ActiveCode->whereIn('number', $users);
        $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
        if($query) $ActiveCode = $ActiveCode->where('active_codes.user_id', $resID)->
        where(function ($q) use ($query) {
            return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
        });
        $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        
        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency = 0;

            $active->user = User::find($active->user_id);
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                $active->exist = $userT->exp_date;
                // $active->typecode = $userT->typecode;
                // $active->duration = $userT->duration;
                // $active->day = $userT->day;
                // $active->user_mac = $userT->macadress;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 

                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $active->days = $new_days->format("%a days");
                        }else{
                            $active->days = "0 days";
                        }
                    }
                } 
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                $date2=date_create( date("Y-m-d H:i:s") );
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }
        }
        return Response()->json($ActiveCode);

    }

    public function amolstExpiredItemsByUser(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $model = new ActiveCode;
        $ActiveCode =  $model;

        $users = DB::connection('mysql2')->table('users')
            ->where('exp_date', '<=', strtotime(Date('Y/m/d H:i:s', strtotime("+2 days"))))
            ->where('exp_date', '>', strtotime(date("Y/m/d H:i:s")))
            ->pluck('username');

        $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
        $ActiveCode = $ActiveCode->select('active_codes.*');
        $ActiveCode = $ActiveCode->where('user_id', $resID);
        $ActiveCode = $ActiveCode->whereIn('number', $users);
        $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
        if($query) $ActiveCode = $ActiveCode->where('active_codes.user_id', $resID)->
        where(function ($q) use ($query) {
            return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
        });
        $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency = 0;

            $active->user = User::find($active->user_id);
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                $active->exist = $userT->exp_date;
                // $active->typecode = $userT->typecode;
                // $active->duration = $userT->duration;
                // $active->day = $userT->day;
                // $active->user_mac = $userT->macadress;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 

                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $active->days = $new_days->format("%a days");
                        }else{
                            $active->days = "0 days";
                        }
                    }
                } 
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                $date2=date_create( date("Y-m-d H:i:s") );
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }
        }
        return Response()->json($ActiveCode);

    }

    public function trialItemsByUser(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $model = new ActiveCode;
        $ActiveCode =  $model;

        $packages = DB::connection('mysql2')->table('packages')->where('is_trial', 1)->pluck('id');

        $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
        $ActiveCode = $ActiveCode->select('active_codes.*');
        $ActiveCode = $ActiveCode->where('user_id', $resID);
        $ActiveCode = $ActiveCode->whereIn('active_codes.package_id', $packages);
        $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
        if($query) $ActiveCode = $ActiveCode->where('active_codes.user_id', $resID)->
        where(function ($q) use ($query) {
            return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
        });
        $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency = 0;

            $active->user = User::find($active->user_id);
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                $active->exist = $userT->exp_date;
                // $active->typecode = $userT->typecode;
                // $active->duration = $userT->duration;
                // $active->day = $userT->day;
                // $active->user_mac = $userT->macadress;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 

                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $active->days = $new_days->format("%a days");
                        }else{
                            $active->days = "0 days";
                        }
                    }
                } 
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                $date2=date_create( date("Y-m-d H:i:s") );
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }
        }
        return Response()->json($ActiveCode);

    }

    public function enabledItemsByUser(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $model = new ActiveCode;
        $ActiveCode =  $model;

        $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
        $ActiveCode = $ActiveCode->select('active_codes.*');
        $ActiveCode = $ActiveCode->where('user_id', $resID);
        $ActiveCode = $ActiveCode->where('enabled', 1);
        $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
        if($query) $ActiveCode = $ActiveCode->where('active_codes.user_id', $resID)->
        where(function ($q) use ($query) {
            return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
        });
        $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        
        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency = 0;

            $active->user = User::find($active->user_id);
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                $active->exist = $userT->exp_date;
                // $active->typecode = $userT->typecode;
                // $active->duration = $userT->duration;
                // $active->day = $userT->day;
                // $active->user_mac = $userT->macadress;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 

                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $active->days = $new_days->format("%a days");
                        }else{
                            $active->days = "0 days";
                        }
                    }
                } 
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                            // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                $date2=date_create( date("Y-m-d H:i:s") );
                            // else
                            //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }
        }
        return Response()->json($ActiveCode);

    }

    public function disabledItemsByUser(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $model = new ActiveCode;
        $ActiveCode =  $model;

        $ActiveCode = $ActiveCode->join('users', 'active_codes.user_id', '=', 'users.id');
        $ActiveCode = $ActiveCode->select('active_codes.*');
        $ActiveCode = $ActiveCode->where('user_id', $resID);
        $ActiveCode = $ActiveCode->where('enabled', 0);
        $ActiveCode = $ActiveCode->where('active_codes.deleted_at','=' , Null);
        if($query) $ActiveCode = $ActiveCode->where('active_codes.user_id', $resID)->
        where(function ($q) use ($query) {
            return $q->where('active_codes.number','LIKE', "%{$query}%")->orWhere('active_codes.notes','LIKE', "%{$query}%");
        });
        $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        foreach ($ActiveCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency = 0;

            $active->user = User::find($active->user_id);
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                $active->exist = $userT->exp_date;
                // $active->typecode = $userT->typecode;
                // $active->duration = $userT->duration;
                // $active->day = $userT->day;
                // $active->user_mac = $userT->macadress;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 

                }
                if($active->time !== "" || $active->time !== null) {
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $active->days = $new_days->format("%a days");
                        }else{
                            $active->days = "0 days";
                        }
                    }
                } 
                $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                if(count($users_activity_now) > 0) {
                    $active->online = 1;
                }

                $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $userT->id)->get();
                if(count($users_activity) > 0) {
                    foreach ($users_activity as $activity) {
                        if($activity->user_id == $userT->id) {
                            $active->last_connection = date("Y-m-d", $activity->date_start);
                            $active->flag = $activity->geoip_country_code;
                            $active->user_ip = $activity->user_ip;
                            $active->stream_id = $activity->stream_id;
                            if($active->online == 1) {
                                $active->latency = (100 - $activity->divergence)/20;
                            }

                            $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                                $date2=date_create( date("Y-m-d H:i:s") );
                            $active->last_seen_date = date_diff($date2, $date1);
                            $active->last_seen_date =  $active->last_seen_date->format('%hh %im %ss');
                        }
                    }
                }
                if($active->stream_id != '') {
                    $channels =  DB::connection('mysql2')->table('streams')->find($active->stream_id);
                    if($channels) {
                        $active->stream_name = $channels->stream_display_name;
                    }
                    
                }
            }
        }
        return Response()->json($ActiveCode);

    }

}
