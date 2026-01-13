<?php

namespace App\Http\Controllers;

use App\MagDevice;
use App\User;
use App\ResellerStatistic;
use App\SubResiler;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use DB;

class MagDeviceController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;        
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();
        $subArray = [];

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);
       
        if($user_type !== 'Admin'){
            $model = new MagDevice;
            $ActiveCode =  $model;
            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->whereIn('mag_devices.user_id', $subArray);
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            if($query) $ActiveCode = $ActiveCode->where(function ($q) use ($query) {
                return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            });
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MagDevice;
            $ActiveCode =  $model;

            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            if($query) $ActiveCode = $ActiveCode->Where('mag_devices.mac','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();
            if($get_user_mac) {}
            else{
                $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mag_id' , $active->mag_device_id)->first();
            }

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $date_today = date_create(date('Y-m-d H:i:s'));
            $exp_time = date_create($active->time);
            $new_days = date_diff($date_today,$exp_time);
            // $active->days = $new_days->format("%a days");
            if($active->time > date('Y-m-d')) {
                $active->days = $new_days->format("%a days");
            }else{
                $active->days = "0 days";
            } 

            $active->selected_bouquets = [];

            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $active->has_is_trial = true;
                    $active->userT = $userT;

                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }

                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    if($package) {
                        $active->is_trial = $package->is_trial;
                        $active->has_is_trial = true;
                    }
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                if($package) {
                    $active->is_trial = $package->is_trial;
                    $active->has_is_trial = true;
                }
                
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
    public function store(Request $request) {
        request()->validate([
            'mac' => 'required|unique:mag_devices,mac,NULL,id,deleted_at,NULL',
            'name' => 'required',
            'pack' => 'required',
        ]);
      
        DB::beginTransaction();
        try {
            $mac = $request->mac;
            $user = Auth::user();
            $user_id = auth()->id();
            $user_type = Auth::user()->type;
            $now = date("Y-m-d H:i:s");
            $now = strtotime( "$now" );
            $created_at = $now;
            if($request->is_trial){
                $duration_p = $request->trial_duration;
                $duration_in = $request->trial_duration_in;
            }else {
                $duration_p =$request->duration;
                $duration_in = $request->duration_in;
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
            
            
            if ($duration_p == "365" || $duration_p == "366"){
                $expiredate= strtotime( "+12 month" );
                $string_expiredate = date("Y-m-d", $expiredate);
                }
            elseif ($duration_p == "1"){
                    $expiredate= strtotime( "+1 days" );
                    $string_expiredate = date("Y-m-d", $expiredate);
                }
            elseif ($duration_p == "10"){
                    $expiredate= strtotime( "+10 days" );
                    $string_expiredate = date("Y-m-d", $expiredate);
                }
            elseif ($duration_p == "30"){
                    $expiredate= strtotime( "+1 month" );
                    $string_expiredate = date("Y-m-d", $expiredate);
                }
            elseif ($duration_p == "60"){
                    $expiredate= strtotime( "+2 month" );
                    $string_expiredate = date("Y-m-d", $expiredate);
                }
            elseif ($duration_p == "90"){
                    $expiredate= strtotime( "+3 month" );
                    $string_expiredate = date("Y-m-d", $expiredate);
                }
                
            elseif ($duration_p == "180"){
                    $expiredate= strtotime( "+6 month" );
                    $string_expiredate = date("Y-m-d", $expiredate);
                }
            $i = $duration_p;
            $date   = Carbon::now();
            $expire = $date->addDays($i);
            $exp = $expire->format('Y-m-d H:i:s');
        
            $sld = User::find($user_id);

            if($user_type !='Admin'){
                $ss = 1;
                if($duration_p == 30) {
                    $ss = 0.1;
                }else if($duration_p == 90) {
                    $ss =0.25;
                }else if($duration_p == 180) {
                    $ss =0.5;
                }
                
                if($request->is_trial == 0){
                    if($sld->solde - $ss < 0) {
                        if($sld->gift - $ss < 0) {
                            DB::rollback();
                            return response()->json(['msg'=> 'solde'], 401);
                        }
                    }
                }else{
                    if($sld->solde_test - $ss < 0) {
                        DB::rollback();
                        return response()->json(['msg'=> 'solde'], 401);
                    }
                }
            }

            MagDevice::create([
                'name'              => $request['name'],
                'mac'               => $mac,
                'days'              => $duration_p . ' ' .$duration_in,
                'user_id'           => $user_id,
                'time'              => $exp,
                'notes'             => $request['notes']? $request['notes'] : 'iActive',
                'package_id'        => $request['pack'],
                'pack'              => json_encode($request['pack_list']),
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

            DB::connection('mysql2')->table('users')->insert(
            [
                'member_id'   =>   $user_id,
                'created_by'  =>   $user_id,
                'username'    =>   $request->username,
                'password'    =>   $request->password,
                'admin_notes' =>    $var,
                'reseller_notes' => $kk,
                'package_id'  =>   $request->pack,
                'duration_p'  =>   $duration_p,	
                'duration_in' =>   $duration_in,
                'bouquet'     =>   $request->bouquets,
                'is_trial'    =>   $request->is_trial,
                'allowed_ips' =>   '',
                'allowed_ua'  =>   '',
                'created_at'  =>   $created_at,
                'typecode'    =>   4,
                // 'is_mag'      =>   1,
                'exp_date'    =>  $expiredate,

            ]);
                
            $id = DB::connection('mysql2')->table('users')->orderBy('users.id', 'desc')->first()->id;
            DB::connection('mysql2')->table('mag_devices')->insert(
            [
                'user_id'   =>   $id,
                'mac'       =>   base64_encode($mac),
                'created'    =>  $created_at,
                'watchdog_timeout'    =>  0,
        

            ]);
            DB::connection('mysql2')->table('user_output')->insert(
                [
                    'user_id'   =>     $id,
                    'access_output_id'  =>   1,
                    
                ]);
                
            DB::connection('mysql2')->table('user_output')->insert(
            [
                'user_id'   =>     $id,
                'access_output_id'  =>   2,
                
            ]);
            if($user_type !='Admin'){
                $ss = 1;
                if($duration_p == 30) {
                    $ss = 0.1;
                }else if($duration_p == 90) {
                    $ss =0.25;
                }else if($duration_p == 180) {
                    $ss =0.5;
                }
                if($request->is_trial == 0){
                    if($sld->solde - $ss < 0) {
                        if($sld->gift - $ss < 0) {
                            DB::rollback();
                            return response()->json(['msg'=> 'solde'], 401);
                        }
                    }
                    if($sld->solde - $ss < 0) {
                        if($sld->gift - $ss < 0) {}
                        else{
                            $sld->update([                            
                                'gift' => $sld->gift - $ss
                            ]);
                        }
                    }else{
                        $sld->update([                            
                            'solde' => $sld->solde - $ss
                        ]);
                    }
                    ResellerStatistic::create([
                        'reseller_id' => $user_id,
                        'solde' => $ss,
                        'operation' => 0,
                        'operation_name' => 'mag_device',
                        'slug' => 'create'
                    ]);
                }else{
                    if($sld->solde_test - $ss < 0) {                        
                        if($sld->gift - $ss < 0) {
                            DB::rollback();
                            return response()->json(['msg'=> 'solde'], 401);
                        }
                    }
                    if($sld->solde - $ss < 0) {
                        if($sld->gift - $ss < 0) {}
                        else{
                            $sld->update([                            
                                'gift' => $sld->gift - $ss
                            ]);
                        }
                    }else{
                        $sld->update([                            
                            'solde' => $sld->solde - $ss
                        ]);
                    }
                }
            }
            DB::commit();
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
     * @param  \App\MagDevice  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        request()->validate([
            'mac' => 'required|unique:mag_devices,mac,'.$id,
            'name' => 'required',
        ]);
     
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $current = DB::table('mag_devices')->where('id', $id)->first();
        $xtream = DB::connection('mysql2')->table('users')->where('users.member_id' , $current->user_id)->first();
        $sld = User::find($user_id);

        if($request->pack != $current->package_id){
            if($request->is_trial){
                $duration_p = $request->trial_duration;
                $duration_in = $request->trial_duration_in;
            }else {
                $duration_p =$request->duration;
                $duration_in = $request->duration_in;
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
            else if($duration_p == '366' && $duration_in =='days'){
                $duration_p = '366' ;
                $duration_in = 'days';
            }

            $days = $duration_p . ' ' .$duration_in;

            $i = $duration_p;
            $date   = Carbon::now();
            $expire = $date->addDays($i);
            $exp = $expire->format('Y-m-d H:i:s');

            $time = $exp;
            $expiredate = '';
            if ($duration_p == "365"){
                $expiredate= strtotime( "+12 month" );
                $string_expiredate = date("Y-m-d", $expiredate);
                }
            elseif ($duration_p == "1"){
                    $expiredate= strtotime( "+1 days" );
                    $string_expiredate = date("Y-m-d", $expiredate);
                }
            elseif ($duration_p == "10"){
                    $expiredate= strtotime( "+10 days" );
                    $string_expiredate = date("Y-m-d", $expiredate);
                }
            elseif ($duration_p == "30"){
                    $expiredate= strtotime( "+1 month" );
                    $string_expiredate = date("Y-m-d", $expiredate);
                }
            elseif ($duration_p == "60"){
                    $expiredate= strtotime( "+2 month" );
                    $string_expiredate = date("Y-m-d", $expiredate);
                }
            elseif ($duration_p == "90"){
                    $expiredate= strtotime( "+3 month" );
                    $string_expiredate = date("Y-m-d", $expiredate);
                }
            elseif ($duration_p == "180"){
                    $expiredate= strtotime( "+6 month" );
                    $string_expiredate = date("Y-m-d", $expiredate);
                }
            $exp_date = $expiredate;
         

        }else {
            $days = $current->days;
            $time = $current->time;

            $duration_p = $xtream->duration_p;
            $duration_in = $xtream->duration_in;

            $exp_date =  $xtream->exp_date;

        }

        

        MagDevice::whereId($id)->update([
            'name'              => $request['name'],
            'mac'               => $request->mac,
            'package_id'        => $request['pack'],
            'days'              => $days,
            'notes'             => $request['notes']? $request['notes'] : 'iActive',
            'pack'              => json_encode($request['pack_list']),
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
        $get_current_mag = MagDevice::find($id);
        $mmac = base64_encode($get_current_mag->mac);
        if($get_current_mag->mac != 'Mac Reseted') {
            $get_mag_user = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();
            if($get_mag_user) {}
            else{
                $get_mag_user = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mag_id' , $get_current_mag->mag_device_id)->first();
            }
            
            if($get_mag_user) {
                $tt = DB::connection('mysql2')->table('users')->where('id', $get_mag_user->user_id)->update(
                    [
                        'package_id'        => $request['pack'],
                        'admin_notes'       =>    $var,
                        'reseller_notes'    => $kk,
                        'duration_p'        =>   $duration_p,	
                        'duration_in'       =>   $duration_in, 
                        'bouquet'           =>   $request->bouquets,
                        'is_trial'          =>   $request->is_trial,
                        'macadress'         =>   $request->mac
                    ]
                );

                DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mag_id' , $get_mag_user->mag_id)->update(
                [
                    'mac'   => base64_encode($request->mac),
                ]);
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\MagDevice  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, $type)
    {
        DB::beginTransaction();
        try {
            $current = DB::table('mag_devices')->where('id', $id)->first();
            $mmac = base64_encode($current->mac);
            $ActiveCode = MagDevice::findOrFail($id);
            if($type == 'disabled') {
                MagDevice::whereId($id)->update([
                    'enabled' => 0,
                ]);    
                $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();
                $users = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();            
                DB::connection('mysql2')->table('users')->where('id' , $users->id)->update(
                    [
                        'enabled' => 0,
                    ]
                );    
                DB::connection('mysql2')->table('mag_devices')->where('mac' , $mmac)->update(
                    [
                        'lock_device' => 0,
                    ]
                );
            }else {
                MagDevice::whereId($id)->delete();
                $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();
                if($get_user_mac) {
                    if($type == 'transfer') {                        
                        $users = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();                
                        DB::connection('mysql2')->table('mag_devices')->where('mac' , $mmac)->delete();
                        DB::commit();
                        return response()->json(['login' => $users->username, 'password' => $users->password]);
                    }else{
                        $users = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();                
                        DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->delete();        
                        DB::connection('mysql2')->table('mag_devices')->where('mac' , $mmac)->delete();
                    }                    
                }
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(["error" => "error"], 500);
        }   
    }

    public function enableMagD($id)
    {
        $current = DB::table('mag_devices')->where('id', $id)->first();
        $mmac = base64_encode($current->mac);
        $ActiveCode = MagDevice::findOrFail($id);
        MagDevice::whereId($id)->update([
            'enabled' => 1 ,
        ]);
        
        if($current->mac != 'Mac reseted') {
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();

            $users = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
            
            DB::connection('mysql2')->table('users')->where('id' , $users->id)->update(
                [
                    'enabled' => 1,
                ]
            );

            DB::connection('mysql2')->table('mag_devices')->where('mac' , $mmac)->update(
                [
                    'lock_device' => 1,
                ]
            );
        }
    }

    public function resetMac($id){

        $current = DB::table('mag_devices')->where('id', $id)->first();
        $mmac = base64_encode($current->mac);
        $ActiveCode = MagDevice::findOrFail($id);

        $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();

        MagDevice::whereId($id)->update([
            'mac' => 'Mac Reseted',
            'mag_device_id' => $get_user_mac->mag_id
        ]);

        

        $users = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
        
        DB::connection('mysql2')->table('users')->where('id' , $users->id)->update([
            'macadress' => 'Mac Reseted'
        ]);       
    }


    public function showM3U(Request $request)
    {
        $mac = $request->mac;
        $mag_device = DB::connection('mysql2')->table('mag_devices')->where('mac', base64_encode($mac))->first();
        $info = DB::connection('mysql2')->table('users')->where('users.id' , $mag_device->user_id)->first();
        $site = "http://kingtop10.net:7070/get.php?username=";
        $get_user = User::find(Auth::user()->id);
        if($get_user) {
            if($get_user->host != null) {
                $site = "http://" . $get_user->host . ':7070/get.php?username=';
            }
        }
        $user = $info->username;
        $pass = $info->password;
        $m3u =  $site.$user."&password=".$pass;
        return Response()->json($m3u);
    }   


    public function Renew(Request $request, $mac)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $user_pack = Auth::user()->package_id;
        $oo = "[$user_pack]";
        $yy = json_decode('[' . $oo . ']', true);

        if($user_type != 'Admin'){            
            $dd = DB::connection('mysql2')->table('packages')->select('packages.*')
            ->whereIn('packages.id' , $yy[0])->get();
        }else {
            $dd = DB::connection('mysql2')->table('packages')->select('packages.*')->get();
        }
        $pack_id = $request->package_id;
        $getP = DB::connection('mysql2')->table('packages')->select('packages.*')
            ->where('packages.id' , $pack_id)->first();
        if($getP->is_trial == 1) {
            foreach ($dd as $pack) {
                if($pack->official_duration == '365' || $pack->official_duration == '366') {
                    $pack_id = $pack->id;
                }
            }
        }

        $pack= $request->package_id;
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $user_solde = Auth::user()->solde;
        $mmac = base64_encode($mac);
        $sld = User::find($user_id);
        $current =  DB::table('mag_devices')->where('mac', $mac)->first();
        $old = $current->time; 
        $ee = new Carbon($old);
        if($ee > Carbon::now()) {}
        else{
            $old = Carbon::now();
        }
        $date   = new Carbon($old);

        $current =   DB::table('mag_devices')->where('mac', $mac)->first();

        $current_pack = DB::connection('mysql2')->table('packages')->select('packages.*')
            ->where('id' , $current->package_id)->first();

        if($current_pack->is_trial){
            $duration_p2 = $current_pack->trial_duration;
            $duration_in2 = $current_pack->trial_duration_in;
        }else {
            $duration_p2 =$current_pack->official_duration;
            $duration_in2 = $current_pack->official_duration_in;
        }
        if($duration_p2 == '1' && $duration_in2 =='years'){
            $duration_p2 = '365' ;
            $duration_in2 = 'days';
        }else if($duration_p2 == '1' && $duration_in2 =='months'){
            $duration_p2 = '30' ;
            $duration_in2 = 'days';
        }else if($duration_p2 == '3' && $duration_in2 =='months'){
            $duration_p2 = '90' ;
            $duration_in2 = 'days';
        }else if($duration_p2 == '6' && $duration_in2 =='months'){
            $duration_p2 = '180' ;
            $duration_in2 = 'days';
        }else if($duration_p2 == '24' && $duration_in2 =='hours'){
            $duration_p2 = '1' ;
            $duration_in2 = 'days';
        }else if($duration_p2 == '10' && $duration_in2 =='days'){
            $duration_p2 = '10' ;
            $duration_in2 = 'days';
        }else if($duration_p2 == '2' && $duration_in2 =='months'){
            $duration_p2 = '60' ;
            $duration_in2 = 'days';
        }else if($duration_p2 == '366' && $duration_in2 =='days'){
            $duration_p2 = '366' ;
            $duration_in2 = 'days';
        }

        $addDays = 365;
        if((int)$duration_p2 < 365) {
            $addDays = 365 - (int)$duration_p2;
        }

        $expire = $date->addDays($addDays);
        $exp = $expire->format('Y-m-d H:i:s');

        $now = Carbon::now();
        $length = $now->diff($exp)->days;
        

        if($mac != 'Mac Reseted') {
            DB::table('mag_devices')->where('mac', $mac)->update([
                'days'  =>  $length.' '."days",
                'time'  =>  $exp,
                'package_id' => $pack_id 
            ]);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();

            DB::table('mag_devices')->where('mag_devices.mac' , $mmac)->update([
                'days'  => $length.' '."days",
                'time'  =>  $exp,
                'package_id' => $pack_id 
            ]);                    

            DB::connection('mysql2')->table('users')->where('users.id' , $get_user_mac->user_id)->update(
                [
                    
                    'users.duration_p'  =>   $length,
                    'users.duration_in' =>   'days',
                    'users.exp_date'    =>   strtotime($exp),
                    'is_trial'          => 0,
                    'package_id' => $pack_id,
                    'is_mag' => 0
                ]

            );
        }

        if($getP->is_trial){
            $duration_p = $getP->trial_duration;
            $duration_in = $getP->trial_duration_in;
        }else {
            $duration_p =$getP->official_duration;
            $duration_in = $getP->official_duration_in;
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

        if($user_type !='Admin'){
            $ss = 1;
            if($duration_p == 30) {
                $ss = 0.1;
            }else if($duration_p == 90) {
                $ss =0.25;
            }else if($duration_p == 180) {
                $ss =0.5;
            }                
            if($sld->solde - $ss < 0) {
                if($sld->gift - $ss < 0) {
                    DB::rollback();
                    return response()->json(['msg'=> 'solde'], 401);
                }
            }
            if($sld->solde - $ss < 0) {
                if($sld->gift - $ss < 0) {}
                else{
                    $sld->update([                            
                        'gift' => $sld->gift - $ss
                    ]);
                }
            }else{
                $sld->update([                            
                    'solde' => $sld->solde - $ss
                ]);
            }

            ResellerStatistic::create([
                'reseller_id' => $user_id,
                'solde' => $ss,
                'operation' => 0,
                'operation_name' => 'mag_device',
                'slug' => 'renew'
            ]);
            
        }
    }
    
    
    public function getmag(){
        $rr = DB::connection('mysql2')->table('mag_devices')->orderBy('mag_devices.mag_id', 'DESC')->first();
        dd($rr);
    }

    public function changeDays(Request $request, $mac)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $user_pack = Auth::user()->package_id;
        $oo = "[$user_pack]";
        $yy = json_decode('[' . $oo . ']', true);
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $user_solde = Auth::user()->solde;
        $mmac = base64_encode($mac);
        $current =  DB::table('mag_devices')->where('mac', $mac)->first();
        if($current) {
            $old = $current->time; 
            $new_date = explode(" ", $request->days);
            $date   = Carbon::now();
            $expire = $date->addDays($new_date[0]);
            $exp = $expire->format('Y-m-d H:i:s');
            $now = Carbon::now();
            $length = $now->diff($exp)->days + 1;
            if($mac != 'Mac Reseted') {
                DB::table('mag_devices')->where('mac', $mac)->update([
                    'days'  =>  $length.' '."days",
                    'time'  =>  $exp,
                ]);
                $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();

                DB::table('mag_devices')->where('mag_devices.mac' , $mmac)->update([
                    'days'  => $length.' '."days",
                    'time'  =>  $exp,
                ]);                    

                DB::connection('mysql2')->table('users')->where('users.id' , $get_user_mac->user_id)->update(
                    [
                        
                        'users.duration_p'  =>   $length,
                        'users.duration_in' =>   'days',
                        'users.exp_date'    =>   strtotime($exp),
                        'is_mag' => 0
                    ]

                );
            }

            if($user_type !='Admin'){
                $sld->update([
                    'solde' => $sld->solde - 1
                ]);

                ResellerStatistic::create([
                    'reseller_id' => $user_id,
                    'solde' => 1,
                    'operation' => 0,
                    'operation_name' => 'mag_device',
                    'slug' => 'renew'
                ]);
            }
        }

    }

    public function byReseller(Request $req, $resID)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');
       
        if($user_type !== 'Admin'){
                $model = new MagDevice;
                $ActiveCode =  $model;
                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

            

        }else {
                $model = new MagDevice;
                $ActiveCode =  $model;

                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });                
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $active->selected_bouquets = [];
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                $active->user_t = $userT;
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }
                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    $active->is_trial = $package->is_trial;
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                $active->is_trial = $package->is_trial;
            }
        }
        
        return Response()->json($ActiveCode);
    }


    public function expiredItems() {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;        
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();
        $subArray = [];

        $users = DB::connection('mysql2')->table('users')->where('exp_date', '<', strtotime(date("Y/m/d H:i:s")))->pluck('id');
        $magdevices = DB::connection('mysql2')->table('mag_devices')->whereIn('user_id', $users)->pluck('mac');
        $mag_array = [];
        
        foreach ($magdevices as  $mm) {
            array_push($mag_array, base64_decode($mm));
        }


        // $mag_pluck = MagDevice::where('');

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);
       
        if($user_type !== 'Admin'){
            $model = new MagDevice;
            $ActiveCode =  $model;
            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->whereIn('mag_devices.user_id', $subArray);
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            $ActiveCode = $ActiveCode->whereIn('mag_devices.mac', $mag_array);
            if($query) $ActiveCode = $ActiveCode->where(function ($q) use ($query) {
                return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            });
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MagDevice;
            $ActiveCode =  $model;

            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            $ActiveCode = $ActiveCode->whereIn('mag_devices.mac', $mag_array);
            if($query) $ActiveCode = $ActiveCode->Where('mag_devices.mac','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();
            if($get_user_mac) {}
            else{
                $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mag_id' , $active->mag_device_id)->first();
            }

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $date_today = date_create(date('Y-m-d H:i:s'));
            $exp_time = date_create($active->time);
            $new_days = date_diff($date_today,$exp_time);
            // $active->days = $new_days->format("%a days");
            if($active->time > date('Y-m-d')) {
                $active->days = $new_days->format("%a days");
            }else{
                $active->days = "0 days";
            } 

            $active->selected_bouquets = [];

            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $active->has_is_trial = true;
                    $active->userT = $userT;

                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }

                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    if($package) {
                        $active->is_trial = $package->is_trial;
                        $active->has_is_trial = true;
                    }
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                if($package) {
                    $active->is_trial = $package->is_trial;
                    $active->has_is_trial = true;
                }
                
            }
        }
        
        return Response()->json($ActiveCode);
    }

    public function expiredItemsByUser(Request $req, $resID) {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $users = DB::connection('mysql2')->table('users')->where('exp_date', '<', strtotime(date("Y/m/d H:i:s")))->pluck('id');
        $magdevices = DB::connection('mysql2')->table('mag_devices')->whereIn('user_id', $users)->pluck('mac');
        $mag_array = [];
        
        foreach ($magdevices as  $mm) {
            array_push($mag_array, base64_decode($mm));
        }
        
        $query = request('query');
       
        if($user_type !== 'Admin'){
                $model = new MagDevice;
                $ActiveCode =  $model;
                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->whereIn('mag_devices.mac', $mag_array);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

            

        }else {
                $model = new MagDevice;
                $ActiveCode =  $model;

                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->whereIn('mag_devices.mac', $mag_array);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });                
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $active->selected_bouquets = [];
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                $active->user_t = $userT;
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }
                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    $active->is_trial = $package->is_trial;
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                $active->is_trial = $package->is_trial;
            }
        }
        
        return Response()->json($ActiveCode);
    }

    public function onlineItems() {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;        
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();
        $subArray = [];

        $users_activity_now_pluck = DB::connection('mysql2')->table('user_activity_now')->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->pluck('user_id');
        $users = DB::connection('mysql2')->table('users')->whereIn('id', $users_activity_now_pluck)->pluck('id');
        $magdevices = DB::connection('mysql2')->table('mag_devices')->whereIn('user_id', $users)->pluck('mac');
        $mag_array = [];
        
        foreach ($magdevices as  $mm) {
            array_push($mag_array, base64_decode($mm));
        }

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);
       
        if($user_type !== 'Admin'){
            $model = new MagDevice;
            $ActiveCode =  $model;
            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->whereIn('mag_devices.user_id', $subArray);
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            $ActiveCode = $ActiveCode->whereIn('mag_devices.mac', $mag_array);
            if($query) $ActiveCode = $ActiveCode->where(function ($q) use ($query) {
                return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            });
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MagDevice;
            $ActiveCode =  $model;

            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            $ActiveCode = $ActiveCode->whereIn('mag_devices.mac', $mag_array);
            if($query) $ActiveCode = $ActiveCode->Where('mag_devices.mac','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();
            if($get_user_mac) {}
            else{
                $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mag_id' , $active->mag_device_id)->first();
            }

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $date_today = date_create(date('Y-m-d H:i:s'));
            $exp_time = date_create($active->time);
            $new_days = date_diff($date_today,$exp_time);
            // $active->days = $new_days->format("%a days");
            if($active->time > date('Y-m-d')) {
                $active->days = $new_days->format("%a days");
            }else{
                $active->days = "0 days";
            } 

            $active->selected_bouquets = [];

            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $active->has_is_trial = true;
                    $active->userT = $userT;

                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }

                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    if($package) {
                        $active->is_trial = $package->is_trial;
                        $active->has_is_trial = true;
                    }
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                if($package) {
                    $active->is_trial = $package->is_trial;
                    $active->has_is_trial = true;
                }
                
            }
        }
        
        return Response()->json($ActiveCode);
    }

    public function onlineItemsByUser(Request $req, $resID) {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $users_activity_now_pluck = DB::connection('mysql2')->table('user_activity_now')->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->pluck('user_id');
        $users = DB::connection('mysql2')->table('users')->whereIn('id', $users_activity_now_pluck)->pluck('id');
        $magdevices = DB::connection('mysql2')->table('mag_devices')->whereIn('user_id', $users)->pluck('mac');
        $mag_array = [];

        foreach ($magdevices as  $mm) {
            array_push($mag_array, base64_decode($mm));
        }
       
        if($user_type !== 'Admin'){
                $model = new MagDevice;
                $ActiveCode =  $model;
                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->whereIn('mag_devices.mac', $mag_array);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

            

        }else {
                $model = new MagDevice;
                $ActiveCode =  $model;

                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->whereIn('mag_devices.mac', $mag_array);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });                
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $active->selected_bouquets = [];
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                $active->user_t = $userT;
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }
                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    $active->is_trial = $package->is_trial;
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                $active->is_trial = $package->is_trial;
            }
        }
        
        return Response()->json($ActiveCode);
    }

    public function amolstExpiredItems() {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;        
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();
        $subArray = [];

        

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);

        $users = DB::connection('mysql2')->table('users')
            ->where('exp_date', '<=', strtotime(Date('Y/m/d H:i:s', strtotime("+2 days"))))
            ->where('exp_date', '>', strtotime(date("Y/m/d H:i:s")))
            ->pluck('id');

        $magdevices = DB::connection('mysql2')->table('mag_devices')->whereIn('user_id', $users)->pluck('mac');
        $mag_array = [];

        foreach ($magdevices as  $mm) {
            array_push($mag_array, base64_decode($mm));
        }
       
        if($user_type !== 'Admin'){
            $model = new MagDevice;
            $ActiveCode =  $model;
            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->whereIn('mag_devices.user_id', $subArray);
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            $ActiveCode = $ActiveCode->whereIn('mag_devices.mac', $mag_array);
            if($query) $ActiveCode = $ActiveCode->where(function ($q) use ($query) {
                return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            });
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MagDevice;
            $ActiveCode =  $model;

            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            $ActiveCode = $ActiveCode->whereIn('mag_devices.mac', $mag_array);
            if($query) $ActiveCode = $ActiveCode->Where('mag_devices.mac','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();
            if($get_user_mac) {}
            else{
                $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mag_id' , $active->mag_device_id)->first();
            }

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $date_today = date_create(date('Y-m-d H:i:s'));
            $exp_time = date_create($active->time);
            $new_days = date_diff($date_today,$exp_time);
            // $active->days = $new_days->format("%a days");
            if($active->time > date('Y-m-d')) {
                $active->days = $new_days->format("%a days");
            }else{
                $active->days = "0 days";
            } 

            $active->selected_bouquets = [];

            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $active->has_is_trial = true;
                    $active->userT = $userT;

                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }

                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    if($package) {
                        $active->is_trial = $package->is_trial;
                        $active->has_is_trial = true;
                    }
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                if($package) {
                    $active->is_trial = $package->is_trial;
                    $active->has_is_trial = true;
                }
                
            }
        }
        
        return Response()->json($ActiveCode);
    }

    public function amolstExpiredItemsByUser(Request $req, $resID) {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $users = DB::connection('mysql2')->table('users')
            ->where('exp_date', '<=', strtotime(Date('Y/m/d H:i:s', strtotime("+2 days"))))
            ->where('exp_date', '>', strtotime(date("Y/m/d H:i:s")))
            ->pluck('id');

        $magdevices = DB::connection('mysql2')->table('mag_devices')->whereIn('user_id', $users)->pluck('mac');
        $mag_array = [];

        foreach ($magdevices as  $mm) {
            array_push($mag_array, base64_decode($mm));
        }
       
        if($user_type !== 'Admin'){
                $model = new MagDevice;
                $ActiveCode =  $model;
                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->whereIn('mag_devices.mac', $mag_array);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

            

        }else {
                $model = new MagDevice;
                $ActiveCode =  $model;

                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->whereIn('mag_devices.mac', $mag_array);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });                
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $active->selected_bouquets = [];
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                $active->user_t = $userT;
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }
                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    $active->is_trial = $package->is_trial;
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                $active->is_trial = $package->is_trial;
            }
        }
        
        return Response()->json($ActiveCode);
    }

    public function trialItems() {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;        
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();
        $subArray = [];

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);

        $packages = DB::connection('mysql2')->table('packages')->where('is_trial', 1)->pluck('id');
       
        if($user_type !== 'Admin'){
            $model = new MagDevice;
            $ActiveCode =  $model;
            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->whereIn('mag_devices.user_id', $subArray);
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            $ActiveCode = $ActiveCode->whereIn('mag_devices.package_id', $packages);
            if($query) $ActiveCode = $ActiveCode->where(function ($q) use ($query) {
                return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            });
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MagDevice;
            $ActiveCode =  $model;

            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            $ActiveCode = $ActiveCode->whereIn('mag_devices.package_id', $packages);
            if($query) $ActiveCode = $ActiveCode->Where('mag_devices.mac','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();
            if($get_user_mac) {}
            else{
                $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mag_id' , $active->mag_device_id)->first();
            }

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $date_today = date_create(date('Y-m-d H:i:s'));
            $exp_time = date_create($active->time);
            $new_days = date_diff($date_today,$exp_time);
            // $active->days = $new_days->format("%a days");
            if($active->time > date('Y-m-d')) {
                $active->days = $new_days->format("%a days");
            }else{
                $active->days = "0 days";
            } 

            $active->selected_bouquets = [];

            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $active->has_is_trial = true;
                    $active->userT = $userT;

                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }

                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    if($package) {
                        $active->is_trial = $package->is_trial;
                        $active->has_is_trial = true;
                    }
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                if($package) {
                    $active->is_trial = $package->is_trial;
                    $active->has_is_trial = true;
                }
                
            }
        }
        
        return Response()->json($ActiveCode);
    }

    public function trialItemsByUser(Request $req, $resID) {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        $packages = DB::connection('mysql2')->table('packages')->where('is_trial', 1)->pluck('id');
       
        if($user_type !== 'Admin'){
                $model = new MagDevice;
                $ActiveCode =  $model;
                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->whereIn('mag_devices.package_id', $packages);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }else {
                $model = new MagDevice;
                $ActiveCode =  $model;

                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->whereIn('mag_devices.package_id', $packages);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });                
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $active->selected_bouquets = [];
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                $active->user_t = $userT;
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }
                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    $active->is_trial = $package->is_trial;
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                $active->is_trial = $package->is_trial;
            }
        }
        
        return Response()->json($ActiveCode);
    }

    public function disabledItems() {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;        
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();
        $subArray = [];

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);
       
        if($user_type !== 'Admin'){
            $model = new MagDevice;
            $ActiveCode =  $model;
            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->whereIn('mag_devices.user_id', $subArray);
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            $ActiveCode = $ActiveCode->where('enabled', 0);
            if($query) $ActiveCode = $ActiveCode->where(function ($q) use ($query) {
                return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            });
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MagDevice;
            $ActiveCode =  $model;

            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            $ActiveCode = $ActiveCode->where('enabled', 0);
            if($query) $ActiveCode = $ActiveCode->Where('mag_devices.mac','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();
            if($get_user_mac) {}
            else{
                $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mag_id' , $active->mag_device_id)->first();
            }

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $date_today = date_create(date('Y-m-d H:i:s'));
            $exp_time = date_create($active->time);
            $new_days = date_diff($date_today,$exp_time);
            // $active->days = $new_days->format("%a days");
            if($active->time > date('Y-m-d')) {
                $active->days = $new_days->format("%a days");
            }else{
                $active->days = "0 days";
            } 

            $active->selected_bouquets = [];

            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $active->has_is_trial = true;
                    $active->userT = $userT;

                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }

                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    if($package) {
                        $active->is_trial = $package->is_trial;
                        $active->has_is_trial = true;
                    }
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                if($package) {
                    $active->is_trial = $package->is_trial;
                    $active->has_is_trial = true;
                }
                
            }
        }
        
        return Response()->json($ActiveCode);
    }

    public function disabledItemsByUser(Request $req, $resID) {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');
       
        if($user_type !== 'Admin'){
                $model = new MagDevice;
                $ActiveCode =  $model;
                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->where('enabled', 0);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

            

        }else {
                $model = new MagDevice;
                $ActiveCode =  $model;

                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->where('enabled', 0);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });                
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $active->selected_bouquets = [];
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                $active->user_t = $userT;
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }
                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    $active->is_trial = $package->is_trial;
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                $active->is_trial = $package->is_trial;
            }
        }
        
        return Response()->json($ActiveCode); 
    }

    public function enabledItems() {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;        
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();
        $subArray = [];

        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }

        array_push($subArray, $user_id);
       
        if($user_type !== 'Admin'){
            $model = new MagDevice;
            $ActiveCode =  $model;
            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->whereIn('mag_devices.user_id', $subArray);
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            $ActiveCode = $ActiveCode->where('enabled', 1);
            if($query) $ActiveCode = $ActiveCode->where(function ($q) use ($query) {
                return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            });
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MagDevice;
            $ActiveCode =  $model;

            $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
            $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
            $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
            $ActiveCode = $ActiveCode->where('enabled', 1);
            if($query) $ActiveCode = $ActiveCode->Where('mag_devices.mac','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                                                ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
            $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();
            if($get_user_mac) {}
            else{
                $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mag_id' , $active->mag_device_id)->first();
            }

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $date_today = date_create(date('Y-m-d H:i:s'));
            $exp_time = date_create($active->time);
            $new_days = date_diff($date_today,$exp_time);
            // $active->days = $new_days->format("%a days");
            if($active->time > date('Y-m-d')) {
                $active->days = $new_days->format("%a days");
            }else{
                $active->days = "0 days";
            } 

            $active->selected_bouquets = [];

            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $active->has_is_trial = false;
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $active->has_is_trial = true;
                    $active->userT = $userT;

                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }

                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    if($package) {
                        $active->is_trial = $package->is_trial;
                        $active->has_is_trial = true;
                    }
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                if($package) {
                    $active->is_trial = $package->is_trial;
                    $active->has_is_trial = true;
                }
                
            }
        }
        
        return Response()->json($ActiveCode);
    }

    public function enabledItemsByUser(Request $req, $resID) {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');
       
        if($user_type !== 'Admin'){
                $model = new MagDevice;
                $ActiveCode =  $model;
                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->where('enabled', 1);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

            

        }else {
                $model = new MagDevice;
                $ActiveCode =  $model;

                $ActiveCode = $ActiveCode->join('users', 'mag_devices.user_id', '=', 'users.id');
                $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID);
                $ActiveCode = $ActiveCode->with('user')->select('mag_devices.*','users.name AS UserName');
                $ActiveCode = $ActiveCode->where('mag_devices.deleted_at','=' , Null);
                $ActiveCode = $ActiveCode->where('enabled', 1);
                if($query) $ActiveCode = $ActiveCode->where('mag_devices.user_id', $resID)->where(function ($q) use ($query) {
                    return $q->where('mag_devices.mac','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.name','LIKE', "%{$query}%")
                    ->orWhere('mag_devices.notes','LIKE', "%{$query}%");
                });                
                $ActiveCode = $ActiveCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($ActiveCode as $active) {
            $mmac = base64_encode($active->mac);
            $active->user = User::find($active->user_id);
            $get_user_mac = DB::connection('mysql2')->table('mag_devices')->where('mag_devices.mac' , $mmac)->first();

            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->last_seen_date = "";
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;

            $active->selected_bouquets = [];
            if($get_user_mac) {
                $userT = DB::connection('mysql2')->table('users')->where('id' , $get_user_mac->user_id)->first();
                $active->user_t = $userT;
                if($userT) {
                    $active->selected_bouquets = json_decode($userT->bouquet);
                    $active->is_trial = $userT->is_trial;
                    $users_activity_now = DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->get();
                    if(count($users_activity_now) > 0) {
                        $active->online = 1;
                    }
                    $users_activity = $active->online == 1 ? DB::connection('mysql2')->table('user_activity_now')->where('user_id', $userT->id)->orderBy('activity_id', 'desc')->get() : DB::connection('mysql2')->table('user_activity')->where('user_id', $userT->id)->get();
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
                                if($activity->date_end == null || $activity->date_end == "" || empty($activity->date_end))
                                    $date2=date_create( date("Y-m-d H:i:s") );
                                else
                                    $date2=date_create(date("Y-m-d H:i:s", $activity->date_end));
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

                }else{
                    $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                    $active->is_trial = $package->is_trial;
                }
            }else{
                $package = DB::connection('mysql2')->table('packages')->where('id' , $active->package_id)->first();
                $active->is_trial = $package->is_trial;
            }
        }
        
        return Response()->json($ActiveCode);
    }

}
