<?php

namespace App\Http\Controllers;

use App\User;
use App\ActiveCode;
use App\MultiCode;
use App\MasterCode;
use App\MagDevice;
use App\ResellerStatistic;
use App\SubResiler;
use App\Vpn;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Client;
use DB;

class UtilisateurController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $subRes = SubResiler::select('user_id')->where('res_id', $user_id)->get();

        $subArray = [];

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);

        $query = request('query');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->select('user_id', 'mac')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search($mag->mac, $macList) != false) {
                array_push($mag_users,  $magDevices[array_search($mag->mac, $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users);
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->whereIn('member_id', $subArray)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users);
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        } 
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);

        // Get all VPN statuses in one query for efficiency
        $userIds = $users->pluck('id')->toArray();
        $usernames = $users->pluck('username')->toArray();
        $vpnStatuses = Vpn::whereIn('username', $usernames)->pluck('username')->toArray();

        foreach ($users as $user) {
            $user_owner = User::select('name')->find($user->member_id);
            $user->owner = $user_owner;
            $user->mac = $user->macadress;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
                $date_today = date_create(date('Y-m-d H:i:s'));
                $exp_time = date_create(date("Y-m-d H:i:s", $user->exp_date));
                $new_days = date_diff($date_today,$exp_time);
                if(date("Y-m-d", $user->exp_date) > date('Y-m-d')) {
                    $user->package_name = $new_days->format("%a days");
                }else{
                    $user->package_name = "0 days";
                }                
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            // $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();           

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            $user->online = 0;

            $users_activity_now = DB::connection('mysql2')->table('con_activities')->select('id')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";
            $user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->select('date_start', 'geoip_country_code', 'user_ip', 'stream_id', 'divergence', 'user_id')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->select('date_start', 'geoip_country_code', 'user_ip', 'stream_id', 'divergence', 'user_id')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                            $date2=date_create( date("Y-m-d H:i:s") );
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->select('stream_display_name')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }

            // Add VPN status to user
            $user->has_vpn = in_array($user->username, $vpnStatuses);

        }

        return Response()->json($users);
    }

    public function store(Request $request)
    {
        request()->validate([
            'username' => 'required',
            'password' => 'required',
            'pack' => 'required',
        ],
        [
            'username.required'   => 'please type a username',
            'password.required'   => 'please type a password',
            'pack.required'   => 'please Choose Package',
        ]);

        DB::beginTransaction();

        try {
            $user_esxist = DB::connection('mysql2')->table('users')->where('username', $request->username)->get();
            if(count($user_esxist)>0) { return response()->json( ["message"=>"The given data was invalid.", 'errors' => array('username' => ['the name already exists'])], 422); }

            $user = Auth::user();
            $user_id = auth()->id();
            $user_type = Auth::user()->type;

            $now = date("Y-m-d H:i:s");
            $now = strtotime( "$now" );
            $created_at = $now;

            $pack = DB::connection('mysql2')->table('packages')->find($request->pack);

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
            }else if($duration_p == '366' && $duration_in =='days'){
                $duration_p = '366' ;
                $duration_in = 'days';
            }

            // if($duration_p == '1' && $duration_in =='years'){
            //     $duration_p = '365' ;
            //     $duration_in = 'days';
            // }else if($duration_p == '1' && $duration_in =='months'){
            //     $duration_p = '30' ;
            //     $duration_in = 'days';
            // }else if($duration_p == '3' && $duration_in =='months'){
            //     $duration_p = '90' ;
            //     $duration_in = 'days';
            // }else if($duration_p == '6' && $duration_in =='months'){
            //     $duration_p = '180' ;
            //     $duration_in = 'days';
            // }else if($duration_p == '24' && $duration_in =='hours'){
            //     $duration_p = '1' ;
            //     $duration_in = 'days';
            // }
            // else if($duration_p == '10' && $duration_in =='days'){
            //     $duration_p = '10' ;
            //     $duration_in = 'days';
            // }
            // else if($duration_p == '2' && $duration_in =='months'){
            //     $duration_p = '60' ;
            //     $duration_in = 'days';
            // }else if($duration_p == '366' && $duration_in =='days'){
            //     $duration_p = '366' ;
            //     $duration_in = 'days';
            // }

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
                        return response()->json(['msg'=> 'solde'], 500);
                    }
                }else{
                    if($sld->solde_test - $ss < 0) {
                        DB::rollback();
                        return response()->json(['msg'=> 'solde'], 500);
                    }
                }
            }

            // $i = $duration_p;
            // $date   = Carbon::now();
            // $expire = $date->addDays($i);
            // $exp = $expire->format('Y-m-d H:i:s');
            $expiredate= strtotime( "+".$duration_p . " " . $duration_in );
            $exp = date("Y-m-d H:i:s", $expiredate);
            
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

            DB::connection('mysql2')->table('users')->insert(
            [
                'member_id'   =>   $user_id,
                'created_by'  =>   $user_id,
                'username'    =>   $request->username,
                'password'    =>   $request->password,
                'admin_notes' =>    $var,
                'reseller_notes' => $kk,
                'package_id'  =>   $request->pack,
                // 'duration_p'  =>   $duration_p, 
                // 'duration_in' =>   $duration_in,
                'bouquet'     =>   $request->bouquets,
                'is_trial'    =>   $pack->is_trial,
                'allowed_ips' =>   '',
                'allowed_ua'  =>   '',
                'created_at'  =>   $created_at,
                // 'typecode'    =>   2,
                'exp_date'    => strtotime($exp),
                'forced_country' => "",
                'play_token'=>"",
                'output'    =>'["m3u8","ts","rtmp"]'

            ]);
                

            $id = DB::connection('mysql2')->table('users')->orderBy('users.id', 'desc')->first()->id;

            $code = '';
            if($request->is_trial != "1" && $request->is_trial != 1){
                $post_data = [
                    'count' => 1,
                    'owner_id' => Auth::user()->id,
                ];
                $headers = [
                    'User-Agent' => 'arcapi',
                ];
                $client = new Client();
                $response = $client->post('https://arcplayer.com/api/free_coupon', [
                    'json' => $post_data,
                    'headers' => $headers
                ]);
                $responseData = json_decode($response->getBody(), true);
                $code = $responseData['codes'][0];
            }
                
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
                        'operation_name' => 'user',
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

    public function update(Request $request, $id)
    {

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);
            $is_owner = DB::connection('mysql2')->table('users')->where('id' , $id)->whereIn("member_id", $res)->first();
            if($is_owner) {}
            else{
                return response(['message'=>'Wrong user'], 403);
            }
        }

        
        request()->validate([
            'username' => 'required',
            'password' => 'required',
            'pack' => 'required',
        ],
        [
            'username.required'   => 'please type a username',
            'password.required'   => 'please type a password',
            'pack.required'   => 'please Choose Package',
        ]);

        $user_esxist = DB::connection('mysql2')->table('users')->where('username', $request->username)->where('id', '!=', $id)->get();
        if(count($user_esxist)>0) { return response()->json( ["message"=>"The given data was invalid.", 'errors' => array('username' => ['the name already exists'])], 422); }

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $now = date("Y-m-d H:i:s");
        $now = strtotime( "$now" );
        $created_at = $now;

        $pack = DB::connection('mysql2')->table('packages')->find($request->pack);

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

        // if($duration_p == '1' && $duration_in =='years'){
        //     $duration_p = '365' ;
        //     $duration_in = 'days';
        // }else if($duration_p == '1' && $duration_in =='months'){
        //     $duration_p = '30' ;
        //     $duration_in = 'days';

        // }else if($duration_p == '3' && $duration_in =='months'){
        //     $duration_p = '90' ;
        //     $duration_in = 'days';
        // }else if($duration_p == '6' && $duration_in =='months'){
        //     $duration_p = '180' ;
        //     $duration_in = 'days';
        // }else if($duration_p == '24' && $duration_in =='hours'){
        //     $duration_p = '1' ;
        //     $duration_in = 'days';
        // }
        // else if($duration_p == '10' && $duration_in =='days'){
        //     $duration_p = '10' ;
        //     $duration_in = 'days';
        // }
        // else if($duration_p == '2' && $duration_in =='months'){
        //     $duration_p = '60' ;
        //     $duration_in = 'days';
        // }

        $i = $duration_p;
        $date   = Carbon::now();
        $expire = $date->addDays($i);
        $exp = $expire->format('Y-m-d H:i:s');
        
        $sld = User::find($user_id);
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

        // Update VPN table if user has VPN activated (BEFORE updating the user)
        $oldUser = DB::connection('mysql2')->table('users')->where('id', $id)->first();
        if ($oldUser) {
            $vpn = \App\Vpn::where('username', $oldUser->username)->first();
            if ($vpn) {
                $vpn->update([
                    'username' => $request->username,
                    'password' => $request->password
                ]);
            }
        }

        DB::connection('mysql2')->table('users')->where('id' , $id)->update(
        [
            // 'created_by'  =>   $user_id,
            'username'    =>   $request->username,
            'password'    =>   $request->password,
            'admin_notes' =>    $var,
            'reseller_notes' => $kk,
            'package_id'  =>   $request->pack,
            // 'duration_p'  =>   $duration_p,
            // 'duration_in' =>   $duration_in,
            'bouquet'     =>   $request->bouquets,
            'is_trial'    =>   $pack->is_trial,
            'allowed_ips' =>   '',
            'allowed_ua'  =>   '',
            'created_at'  =>   $created_at,
            // 'typecode'    =>   2,
            'forced_country' => "",
            'play_token'=>"",
            'output'    =>'["m3u8","ts","rtmp"]'

        ]);

       $id = DB::connection('mysql2')->table('users')->orderBy('users.id', 'desc')->first()->id;
    }

    public function showM3U(Request $request) {

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);
            $is_owner = DB::connection('mysql2')->table('users')->where('username' , $request->username)->whereIn("member_id", $res)->first();
            if($is_owner) {}
            else{
                return response(['message'=>'Wrong user'], 403);
            }
        }

        $code = $request->username;
        $info = DB::connection('mysql2')->table('users')->where('users.username' , $code)->first();
        $site = "http://atrupo4k.com:80/get.php?username=";
        $user = Auth::user();
        if($user->host != null) {
            $site = "http://" . $user->host . ':80/get.php?username=';
        }
        $user = $info->username;
        $pass = $info->password;
        $m3u =  $site.$user."&password=".$pass;
        return Response()->json($m3u);
    } 

    public function resetMac($id) {

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);
            $is_owner = DB::connection('mysql2')->table('users')->where('id' , $id)->whereIn("member_id", ["3666"])->get();
            if($is_owner) {}
            else{
                return response(['message'=>'Wrong user'], 403);
            }
        }

        $current = DB::connection('mysql2')->table('users')->where('id', $id)->first();
        ActiveCode::where('number' , $current->username)->update(['mac' => 'Mac Reseted']);
        DB::connection('mysql2')->table('users')->where('users.username' , $current->username)->update(['users.macadress' => 'Mac Reseted']);
    }

    public function Renew(Request $request, $code) {

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);
            $is_owner = DB::connection('mysql2')->table('users')->where('username' , $code)->whereIn("member_id", $res)->first();
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
        //     $dd = DB::connection('mysql2')->table('packages')->select('packages.*')
        //     ->whereIn('packages.id' , $yy[0])->get();
        // }else {
        //     $dd = DB::connection('mysql2')->table('packages')->select('packages.*')->get();
        // }
        // $pack_id = $request->package_id;
        // $getP = DB::connection('mysql2')->table('packages')->select('packages.*')->where('packages.id' , $pack_id)->first();
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
        $user_solde_test = Auth::user()->solde_test;

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

        $current =  DB::connection('mysql2')->table('users')->where('username', $code)->first();
        $old = $current->exp_date; 
        $old = date('Y-m-d H:i:s', $old);

        $ee = new Carbon($old);
        if($ee > Carbon::now()) {}
        else{
            $old = Carbon::now();
        }

        $date   = new Carbon($old);
        $expire = $date->addDays($days);
        $exp = $expire->format('Y-m-d H:i:s');

        $now = Carbon::now();
        $length = $now->diff($exp)->days;
        DB::connection('mysql2')->table('users')->where('users.username' , $code)->update(
            [
                
                // 'users.duration_p'  =>    $length,	
                // 'users.duration_in' =>   'days',
                'users.exp_date'    =>   strtotime($exp),
                'users.is_trial'          => 0,
                // 'users.package_id' => $pack_id,
                'users.is_mag' => 0                        

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

            if($user_type !='Admin'){
                if($sld->solde - $ss < 0) {
                    return response()->json(['msg'=> 'solde'], 401);
                }                    
            }
            $sld->update([
                    
                'solde' => $sld->solde - $ss

            ]);

            ResellerStatistic::create([
                'reseller_id' => $user_id,
                'solde' => $ss,
                'operation' => 0,
                'operation_name' => 'user',
                'slug' => 'renew'
            ]);
            
        }
    }

    public function destroy($id, $type)
    {

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);
            $is_owner = DB::connection('mysql2')->table('users')->where('id' , $id)->whereIn("member_id", $res)->first();
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

            if($type == "disabled") {
                $current = DB::connection('mysql2')->table('users')->where('id', $id)->first();
                DB::commit();
                return DB::connection('mysql2')->table('users')->where('username' , $current->username)->update(
                    [
                        'enabled' => 0,
                    ]
                );
            }else if($type == "delete") {
                $current = DB::connection('mysql2')->table('users')->where('id', $id)->delete();
            }else{
                $current = DB::connection('mysql2')->table('users')->where('id', $id)->first();
                do {
                    $number = 190 . rand(10000,99999);
                    $exist = ActiveCode::where('number', $number)->get();
                } while (count($exist) > 0);
                ActiveCode::create([
                    'len'               => 8,
                    'name'              => $number,
                    'number'            => $number,
                    'days'              => $current->duration_p . ' ' .$current->duration_in,
                    'user_id'           => Auth::user()->id,
                    'notes'             => $current->reseller_notes ? $current->reseller_notes : $current->admin_notes,
                    'package_id'        => $current->package_id,
                    'pack'              => $current->bouquet,
                ]);
                DB::connection('mysql2')->table('users')->where('id', $id)->update(
                    [
                        'username' => $number,
                    ]
                );
                DB::commit();
                return response()->json(['login' => $number, 'password' => $current->password]);
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json($th, 500);
            return response()->json(["error" => "error"], 500);
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
            $is_owner = DB::connection('mysql2')->table('users')->where('id' , $id)->whereIn("member_id", $res)->first();
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

        $new_date = explode(" ", $request->days);

        $date   = Carbon::now();
        $expire = $date->addDays($new_date[0]);
        $exp = $expire->format('Y-m-d H:i:s');

        $now = Carbon::now();
        $length = $now->diff($exp)->days +1;

        DB::connection('mysql2')->table('users')->whereId($id)->update(
            [
                // 'users.duration_p'  =>    $length,	
                // 'users.duration_in' =>   'days',
                'users.exp_date'    =>   strtotime($exp),
                'is_mag' => 0
            ]

        );
    }
    
    public function checkSolde(Request $request) {
        $users = User::where('solde', '<=', 2)->paginate(10);
        return response()->json($users, 200);
    }

    public function byReseller(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search($mag->mac, $macList) != false) {
                array_push($mag_users,  $magDevices[array_search($mag->mac, $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users);
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users);
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        }    
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);
        
        foreach ($users as $user) {
            $user_owner = User::find($user->member_id);
            $user->owner = $user_owner;
            $user->mac = $user->macadress;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();       

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            // $user->package_name = $user->duration_p.' '.$user->duration_in;
            $date_today = date_create(date('Y-m-d H:i:s'));
            $exp_time = date_create(date("Y-m-d H:i:s", $user->exp_date));
            $new_days = date_diff($date_today,$exp_time);
            // $user->package_name = $new_days->format("%a days");
            if(date("Y-m-d", $user->exp_date) > date('Y-m-d')) {
                $user->package_name = $new_days->format("%a days");
            }else{
                $user->package_name = "0 days";
            } 
            $user->online = 0;

            // $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";$user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            // $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->get();
            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('log_con_activities')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                        // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2=date_create( date("Y-m-d H:i:s") );
                        // else
                            // $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }
            
        }
        
        return Response()->json($users);
    }


    public function expiredItems(Request $req) {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $subRes = SubResiler::where('res_id', $user_id)->get();

        $subArray = [];

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);

        $query = request('query');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search(base64_encode($mag->mac), $macList) != false) {
                array_push($mag_users,  $magDevices[array_search(base64_encode($mag->mac), $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('exp_date', '<', strtotime(date("Y/m/d H:i:s")));
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->whereIn('member_id', $subArray)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('exp_date', '<', strtotime(date("Y/m/d H:i:s")));
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        }
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);
        
        foreach ($users as $user) {
            $user_owner = User::find($user->member_id);
            $user->owner = $user_owner;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
                $date_today = date_create(date('Y-m-d H:i:s'));
                $exp_time = date_create(date("Y-m-d H:i:s", $user->exp_date));
                $new_days = date_diff($date_today,$exp_time);
                if(date("Y-m-d", $user->exp_date) > date('Y-m-d')) {
                    $user->package_name = $new_days->format("%a days");
                }else{
                    $user->package_name = "0 days";
                }                
                // $user->package_name = $new_days->format("%a days");
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();           

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            // $user->package_name = $user->duration_p.' '.$user->duration_in;
            $user->online = 0;

            $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";$user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                        // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2=date_create( date("Y-m-d H:i:s") );
                        // else
                        //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }
            
        }
        
        return Response()->json($users);
    }

    public function expiredItemsByUser(Request $req, $resID) {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search(base64_encode($mag->mac), $macList) != false) {
                array_push($mag_users,  $magDevices[array_search(base64_encode($mag->mac), $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('exp_date', '<', strtotime(date("Y/m/d H:i:s")));
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('exp_date', '<', strtotime(date("Y/m/d H:i:s")));
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        }    
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);
        
        foreach ($users as $user) {
            $user_owner = User::find($user->member_id);
            $user->owner = $user_owner;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();       

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            $user->package_name = $user->duration_p.' '.$user->duration_in;
            $user->online = 0;

            $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";$user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                        // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2=date_create( date("Y-m-d H:i:s") );
                        // else
                        //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }
            
        }
        
        return Response()->json($users);
    }

    public function onlineItems() {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $users_activity_now_pluck = DB::connection('mysql2')->table('con_activities')->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->pluck('user_id');

        $subRes = SubResiler::where('res_id', $user_id)->get();

        $subArray = [];

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);

        $query = request('query');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search(base64_encode($mag->mac), $macList) != false) {
                array_push($mag_users,  $magDevices[array_search(base64_encode($mag->mac), $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->whereIn('id', $users_activity_now_pluck);
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->whereIn('member_id', $subArray)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->whereIn('id', $users_activity_now_pluck);
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        }

        // if($query) $users = $users->where('users.username','LIKE', "%{$query}%")
        // ->orWhere('users.macadress','LIKE', "%{$query}%");        
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);
        
        foreach ($users as $user) {
            $user_owner = User::find($user->member_id);
            $user->owner = $user_owner;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
                $date_today = date_create(date('Y-m-d H:i:s'));
                $exp_time = date_create(date("Y-m-d H:i:s", $user->exp_date));
                $new_days = date_diff($date_today,$exp_time);
                if(date("Y-m-d", $user->exp_date) > date('Y-m-d')) {
                    $user->package_name = $new_days->format("%a days");
                }else{
                    $user->package_name = "0 days";
                }                
                // $user->package_name = $new_days->format("%a days");
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();           

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            // $user->package_name = $user->duration_p.' '.$user->duration_in;
            $user->online = 0;

            $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";$user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->select('date_start', 'geoip_country_code', 'user_ip', 'stream_id', 'divergence', 'user_id')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->select('date_start', 'geoip_country_code', 'user_ip', 'stream_id', 'divergence', 'user_id')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                        // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2=date_create( date("Y-m-d H:i:s") );
                        // else
                        //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->select('stream_display_name')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }
            
        }
        
        return Response()->json($users);
    }

    public function onlineItemsByUser(Request $req, $resID) {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        $users_activity_now_pluck = DB::connection('mysql2')->table('con_activities')->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->pluck('user_id');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search(base64_encode($mag->mac), $macList) != false) {
                array_push($mag_users,  $magDevices[array_search(base64_encode($mag->mac), $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->whereIn('id', $users_activity_now_pluck);
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->whereIn('id', $users_activity_now_pluck);
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        }    
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);
        
        foreach ($users as $user) {
            $user_owner = User::find($user->member_id);
            $user->owner = $user_owner;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();       

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            $user->package_name = $user->duration_p.' '.$user->duration_in;
            $user->online = 0;

            $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";$user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->select('date_start', 'geoip_country_code', 'user_ip', 'stream_id', 'divergence', 'user_id')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->select('date_start', 'geoip_country_code', 'user_ip', 'stream_id', 'divergence', 'user_id')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                        // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2=date_create( date("Y-m-d H:i:s") );
                        // else
                        //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }
            
        }
        
        return Response()->json($users);
    }

    public function amolstExpiredItems() {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $subRes = SubResiler::where('res_id', $user_id)->get();

        $subArray = [];

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);

        $query = request('query');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search(base64_encode($mag->mac), $macList) != false) {
                array_push($mag_users,  $magDevices[array_search(base64_encode($mag->mac), $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('exp_date', '<=', strtotime(Date('Y/m/d H:i:s', strtotime("+2 days"))))->where('exp_date', '>', strtotime(date("Y/m/d H:i:s")));
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->whereIn('member_id', $subArray)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('exp_date', '<=', strtotime(Date('Y/m/d H:i:s', strtotime("+2 days"))))->where('exp_date', '>', strtotime(date("Y/m/d H:i:s")));
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        }

        // if($query) $users = $users->where('users.username','LIKE', "%{$query}%")
        // ->orWhere('users.macadress','LIKE', "%{$query}%");        
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);
        
        foreach ($users as $user) {
            $user_owner = User::find($user->member_id);
            $user->owner = $user_owner;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
                $date_today = date_create(date('Y-m-d H:i:s'));
                $exp_time = date_create(date("Y-m-d H:i:s", $user->exp_date));
                $new_days = date_diff($date_today,$exp_time);
                if(date("Y-m-d", $user->exp_date) > date('Y-m-d')) {
                    $user->package_name = $new_days->format("%a days");
                }else{
                    $user->package_name = "0 days";
                }                
                // $user->package_name = $new_days->format("%a days");
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();           

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            // $user->package_name = $user->duration_p.' '.$user->duration_in;
            $user->online = 0;

            $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";$user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                        // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2=date_create( date("Y-m-d H:i:s") );
                        // else
                        //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }
            
        }
        
        return Response()->json($users);
    }

    public function amolstExpiredItemsByUser(Request $req, $resID) {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search(base64_encode($mag->mac), $macList) != false) {
                array_push($mag_users,  $magDevices[array_search(base64_encode($mag->mac), $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('exp_date', '<=', strtotime(Date('Y/m/d H:i:s', strtotime("+2 days"))))->where('exp_date', '>', strtotime(date("Y/m/d H:i:s")));
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('exp_date', '<=', strtotime(Date('Y/m/d H:i:s', strtotime("+2 days"))))->where('exp_date', '>', strtotime(date("Y/m/d H:i:s")));
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        }    
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);
        
        foreach ($users as $user) {
            $user_owner = User::find($user->member_id);
            $user->owner = $user_owner;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();       

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            $user->package_name = $user->duration_p.' '.$user->duration_in;
            $user->online = 0;

            $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";$user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                        // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2=date_create( date("Y-m-d H:i:s") );
                        // else
                        //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }
            
        }
        
        return Response()->json($users);
    }

    public function trialItems() {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $subRes = SubResiler::where('res_id', $user_id)->get();

        $subArray = [];

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);

        $query = request('query');

        $packages = DB::connection('mysql2')->table('packages')->where('is_trial', 1)->pluck('id');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search(base64_encode($mag->mac), $macList) != false) {
                array_push($mag_users,  $magDevices[array_search(base64_encode($mag->mac), $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->whereIn('package_id', $packages);
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->whereIn('member_id', $subArray)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->whereIn('package_id', $packages);
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        }

        // if($query) $users = $users->where('users.username','LIKE', "%{$query}%")
        // ->orWhere('users.macadress','LIKE', "%{$query}%");        
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);
        
        foreach ($users as $user) {
            $user_owner = User::find($user->member_id);
            $user->owner = $user_owner;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
                $date_today = date_create(date('Y-m-d H:i:s'));
                $exp_time = date_create(date("Y-m-d H:i:s", $user->exp_date));
                $new_days = date_diff($date_today,$exp_time);
                if(date("Y-m-d", $user->exp_date) > date('Y-m-d')) {
                    $user->package_name = $new_days->format("%a days");
                }else{
                    $user->package_name = "0 days";
                }                
                // $user->package_name = $new_days->format("%a days");
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();           

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            // $user->package_name = $user->duration_p.' '.$user->duration_in;
            $user->online = 0;

            $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";$user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                        // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2=date_create( date("Y-m-d H:i:s") );
                        // else
                        //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }
            
        }
        
        return Response()->json($users); 
    }

    public function trialItemsByUser(Request $req, $resID) {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        $packages = DB::connection('mysql2')->table('packages')->where('is_trial', 1)->pluck('id');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search(base64_encode($mag->mac), $macList) != false) {
                array_push($mag_users,  $magDevices[array_search(base64_encode($mag->mac), $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->whereIn('package_id', $packages);
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->whereIn('package_id', $packages);
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        }    
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);
        
        foreach ($users as $user) {
            $user_owner = User::find($user->member_id);
            $user->owner = $user_owner;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();       

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            $user->package_name = $user->duration_p.' '.$user->duration_in;
            $user->online = 0;

            $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";$user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                        // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2=date_create( date("Y-m-d H:i:s") );
                        // else
                        //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }
            
        }
        
        return Response()->json($users);
    }

    public function disabledItems() {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $subRes = SubResiler::where('res_id', $user_id)->get();

        $subArray = [];

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);

        $query = request('query');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search(base64_encode($mag->mac), $macList) != false) {
                array_push($mag_users,  $magDevices[array_search(base64_encode($mag->mac), $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('enabled', 0);
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->whereIn('member_id', $subArray)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('enabled', 0);
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        }

        // if($query) $users = $users->where('users.username','LIKE', "%{$query}%")
        // ->orWhere('users.macadress','LIKE', "%{$query}%");        
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);
        
        foreach ($users as $user) {
            $user_owner = User::find($user->member_id);
            $user->owner = $user_owner;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
                $date_today = date_create(date('Y-m-d H:i:s'));
                $exp_time = date_create(date("Y-m-d H:i:s", $user->exp_date));
                $new_days = date_diff($date_today,$exp_time);
                if(date("Y-m-d", $user->exp_date) > date('Y-m-d')) {
                    $user->package_name = $new_days->format("%a days");
                }else{
                    $user->package_name = "0 days";
                }                
                // $user->package_name = $new_days->format("%a days");
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();           

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            // $user->package_name = $user->duration_p.' '.$user->duration_in;
            $user->online = 0;

            $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";$user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                        // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2=date_create( date("Y-m-d H:i:s") );
                        // else
                        //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }
            
        }
        
        return Response()->json($users);
    }

    public function disabledItemsByUser(Request $req, $resID) {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search(base64_encode($mag->mac), $macList) != false) {
                array_push($mag_users,  $magDevices[array_search(base64_encode($mag->mac), $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('enabled', 0);
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('enabled', 0);
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        }    
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);
        
        foreach ($users as $user) {
            $user_owner = User::find($user->member_id);
            $user->owner = $user_owner;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();       

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            $user->package_name = $user->duration_p.' '.$user->duration_in;
            $user->online = 0;

            $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";$user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                        // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2=date_create( date("Y-m-d H:i:s") );
                        // else
                        //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }
            
        }
        
        return Response()->json($users);
    }

    public function enabledItems() {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $subRes = SubResiler::where('res_id', $user_id)->get();

        $subArray = [];

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);

        $query = request('query');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search(base64_encode($mag->mac), $macList) != false) {
                array_push($mag_users,  $magDevices[array_search(base64_encode($mag->mac), $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('enabled', 1);
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->whereIn('member_id', $subArray)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('enabled', 1);
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        }

        // if($query) $users = $users->where('users.username','LIKE', "%{$query}%")
        // ->orWhere('users.macadress','LIKE', "%{$query}%");        
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);
        
        foreach ($users as $user) {
            $user_owner = User::find($user->member_id);
            $user->owner = $user_owner;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
                $date_today = date_create(date('Y-m-d H:i:s'));
                $exp_time = date_create(date("Y-m-d H:i:s", $user->exp_date));
                $new_days = date_diff($date_today,$exp_time);
                if(date("Y-m-d", $user->exp_date) > date('Y-m-d')) {
                    $user->package_name = $new_days->format("%a days");
                }else{
                    $user->package_name = "0 days";
                }                
                // $user->package_name = $new_days->format("%a days");
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();           

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            // $user->package_name = $user->duration_p.' '.$user->duration_in;
            $user->online = 0;

            $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";$user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                        // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2=date_create( date("Y-m-d H:i:s") );
                        // else
                        //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }
            
        }
        
        return Response()->json($users);
    }

    public function enabledItemsByUser(Request $req, $resID) {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        $activecode = ActiveCode::pluck('number');
        $multicode = MultiCode::pluck('number');
        $mastercode = MasterCode::pluck('number');
        $magdevice = MagDevice::get();
        
        $magDevices = DB::connection('mysql2')->table('mag_devices')->get()->toArray();
        $macList = array_column($magDevices, 'mac');
        $mag_users = [];
        foreach ($magdevice as $key => $mag) {
            if(array_search(base64_encode($mag->mac), $macList) != false) {
                array_push($mag_users,  $magDevices[array_search(base64_encode($mag->mac), $macList)]->user_id);
            }
        }

        if($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('enabled', 1);
            if($query) $users = $users->where('users.username','LIKE', "%{$query}%")->orWhere('users.admin_notes','LIKE', "%{$query}%");
        }else{
            $users = DB::connection('mysql2')->table('users')->where('member_id', $resID)->whereNotIn('username', $activecode)->whereNotIn('username', $multicode)->whereNotIn('username', $mastercode)->whereNotIn('id', $mag_users)->where('enabled', 1);
            if($query) $users = $users->
            where(function ($q) use ($query) {
                return $q->where('users.username','LIKE', "%{$query}%")->orWhere('users.reseller_notes','LIKE', "%{$query}%");
            });
        }    
        
        $users = $users ->orderBy('id', 'desc')->paginate(20);
        
        foreach ($users as $user) {
            $user_owner = User::find($user->member_id);
            $user->owner = $user_owner;
            if($user->exp_date != "" || $user->exp_date != null){
                $user->time =  date("Y-m-d H:i:s", $user->exp_date);
            }
            $user->created = date("Y-m-d H:i:s", $user->created_at);

            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $user->package_id)->first();       

            $user->notes = $user_type == 'Admin' ? $user->admin_notes : $user->reseller_notes;
            $user->package_name = $user->duration_p.' '.$user->duration_in;
            $user->online = 0;

            $users_activity_now = DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
            if(count($users_activity_now) > 0) {
                $user->online = 1;
            }
            $user->last_connection = 'NEVER';
            $user->flag = '';
            $user->user_ip = '-';
            $user->stream_id = '';
            $user->last_seen_date = "";$user->latency = 0;

            $user->selected_bouquets = json_decode($user->bouquet);

            $users_activity = $user->online == 1 ? DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('con_activities')->where('user_id', $user->id)->get();
            if(count($users_activity) > 0) {
                foreach ($users_activity as $activity) {
                    if($activity->user_id == $user->id) {
                        $user->last_connection = date("Y-m-d", $activity->date_start);
                        $user->flag = $activity->geoip_country_code;
                        $user->user_ip = $activity->user_ip;
                        $user->stream_id = $activity->stream_id;
                        if($user->online == 1) {
                            $user->latency = (100 - $activity->divergence)/20;
                        }

                        $date1=date_create( date("Y-m-d H:i:s", $activity->date_start));
                        // if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                            $date2=date_create( date("Y-m-d H:i:s") );
                        // else
                        //     $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
                        $user->last_seen_date = date_diff($date2, $date1);
                        $user->last_seen_date =  $user->last_seen_date->format('%hh %im %ss');
                    }
                }
            }
            $user->stream_name = '';            

            if($user->stream_id != '') {
                $channels =  DB::connection('mysql2')->table('streams')->find($user->stream_id);
                if($channels) {
                    $user->stream_name = $channels->stream_display_name;
                }
            }
            
        }
        
        return Response()->json($users);
    }
}
