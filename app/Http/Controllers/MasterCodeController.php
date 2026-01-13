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
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');
        if($user_type !== 'Admin'){
            $model = new MasterCode;
            $MasterCode =  $model;

            $MasterCode = $MasterCode->join('users', 'master_codes.user_id', '=', 'users.id');
            $MasterCode = $MasterCode->select('master_codes.*','users.name AS UserName');
            $MasterCode = $MasterCode->where('master_codes.user_id', $user_id);
            $MasterCode = $MasterCode->where('master_codes.deleted_at','=' , Null);

            if($query) $MasterCode = $MasterCode->where(function ($q) use ($query) {
                return $q->where('master_codes.number','LIKE', "%{$query}%")
                ->orWhere('master_codes.mac','LIKE', "%{$query}%")
                ->orWhere('master_codes.name','LIKE', "%{$query}%")
                ->orWhere('master_codes.notes','LIKE', "%{$query}%");
            });
            
            
            $MasterCode = $MasterCode->orderBy('id', 'DESC')->paginate(20);

            

        }else {
            $model = new MasterCode;
            $MasterCode =  $model;

            $MasterCode = $MasterCode->join('users', 'master_codes.user_id', '=', 'users.id');
            $MasterCode = $MasterCode->where('master_codes.user_id', $user_id);
            $MasterCode = $MasterCode->with('user')->select('master_codes.*','users.name AS UserName');
            $MasterCode = $MasterCode->where('master_codes.deleted_at','=' , Null);

            if($query) $MasterCode = $MasterCode->where('master_codes.number','LIKE', "%{$query}%")
                                                                ->orWhere('master_codes.mac','LIKE', "%{$query}%")
                                                                ->orWhere('master_codes.name','LIKE', "%{$query}%")
                                                                ->orWhere('master_codes.notes','LIKE', "%{$query}%");

            $MasterCode = $MasterCode->orderBy('id', 'DESC')->paginate(20);

        }
        foreach ($MasterCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;

            $active->user = User::find($active->user_id);
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();            

            $active->selected_bouquets = [];
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $user_activecode = DB::connection('mysql2')->table('users_master_code')->where('username', $active->number)->first();
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
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    }
                }
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
        
        return Response()->json($MasterCode);
        
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
        request()->validate([
            'number' => 'required|unique:master_codes',
            'name' => 'required',
            'len' => 'required',
            'mac' => 'required',
            'myarray' => 'max:20000',
        ],
        [
       
            'number.required'   => 'please Random Active Codes',
            'name.required'   => 'please put your name',
            'len.required'   => 'please choose length of code',
            'mac.required'   => 'please put mac adress',
            'myarray.max'       => 'Max Adress Mac Is 20000',
        ]);
    }

    public function store(Request $request)
    {
        request()->validate([
            'number' => 'required|unique:master_codes',
            'name' => 'required',
            'len' => 'required',
            'pack' => 'required',
            'mac' => 'required',
            'myarray' => 'max:20000',
        ],
        [
    
            'number.required'   => 'please Random Active Codes',
            'name.required'   => 'please put your name',
            'len.required'   => 'please choose length of code',
            'pack.required'   => 'please Choose Package',
            'mac.required'   => 'please put mac adress',
            'myarray.max'       => 'Max Adress Mac Is 20000',
        ]);

        DB::beginTransaction();
        try {
            $data = explode("\n", $request->mac);
            $array_mac = str_replace(":" , "", $data);
            $now = date("Y-m-d H:i:s");
            $now = strtotime( "$now" );
            $created_at = $now;

            $user = Auth::user();
            $user_id = auth()->id();
            $user_type = Auth::user()->type;
            
            if($request->is_trial){
                $duration_p = $request->trial_duration;
                $duration_in = $request->trial_duration_in;

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

                $i = $duration_p;
                $date   = Carbon::now();
                $expire = $date->addDays($i);
                $exp = $expire->format('Y-m-d H:i:s');

            }else {
                $duration_p =$request->duration;
                $duration_in = $request->duration_in;

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
                $i = $duration_p;
                $date   = Carbon::now();
                $expire = $date->addDays($i);
                $exp = $expire->format('Y-m-d H:i:s');
            }

            

            $sld = User::find($user_id);

            $i = 0;
            
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

            DB::connection('mysql2')->table('users_master_code')->insert(
            [
                'member_id'   =>   $user_id,
                'created_by'  =>   $user_id,
                'username'    =>   $request->number,
                'password'    =>   $request->password,
                // 'macadress'    =>   $array_mac[$i],
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
                // 'typecode'    =>   1,
                'exp_date'    => strtotime($exp),
                'access_output_id'    =>'[1,2,3]'              
            ]);
            $user_master = DB::connection('mysql2')->table('users_master_code')->where('username', $request->number)->where('password', $request->password)->first();
                
            foreach($array_mac as $m){    

                MasterCode::create([
                    'len'               => $request['len'],
                    'name'              => $request['name'],
                    'number'            => $request['number'],
                    'mac'               => $array_mac[$i],
                    'days'              => $duration_p . ' ' .$duration_in,
                    'user_id'           => $user_id,
                    'notes'             => $request['notes']? $request['notes'] : 'iActive',
                    'package_id'        => $request['pack']                                
                ]);
                
                DB::connection('mysql2')->table('mastercode_devices')->insert(
                [
                    'user_id'               => $user_master->id,
                    'mac'                   => $array_mac[$i],
                    'exp_date_master'       => strtotime($exp),
                    'temoin'                => 'NO ACTIVE'

                ]);
                
                if($user_type !='Admin'){
                $sld->update([                                    
                    'solde' => $sld->solde - 1                
                    ]);
                }
                $i++;
            }
            DB::commit();
            return response()->json(['finish' =>'true']);
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

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MasterCode::whereIn('user_id', $res)->where('id', $id)->first();
            if($is_owner) {}
            else{
                return response(['message'=>'Wrong user'], 403);
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
        $mac = str_replace(":" , "", $m);

        $current = DB::table('master_codes')->where('id', $id)->first();
        

        $xtream = DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->where('users.macadress' , $current->mac)->first();        
        
        $sld = User::find($user_id);

        if($request->check === true){

            if($user_type != 'Admin'){
               
                if($request->notes !== null){
                    $kk = $request->notes;
                    $var = '';
                    //  $kk=1;
                }else {
                    // $kk=0;
                    $kk = 'iActive';
                    $var = '';
                }
            }else {
                
                if($request->notes !== null){
                    $var = $request->notes;
                    $kk ='';
                    // $var=1;
                }else {
                    // $var=0;
                    $var = 'iActive';
                    $kk ='';
                }
                
            }
            

            MasterCode::where('number' , $current->number)->update([
                'len'               => $request['len'],
                'name'              => $request['name'],
                'number'            => $request['number'],
                'package_id'        => $request['pack'],
                // 'days'              => $days,
                // 'time'              => $time,
                'user_id'           => $user_id,
                'notes'             => $request['notes']? $request['notes'] : 'iActive',
            ]);

            

            DB::connection('mysql2')->table('users_master_code')->where('users_master_code.username' , $request['number'])->update([
                'users_master_code.package_id' => $request['pack'],
                'users_master_code.username'   => $request['number'],
                'users_master_code.admin_notes' =>    $var,
                'users_master_code.reseller_notes' => $kk,
                // 'users.duration_p'  =>   $duration_p,	
                // 'users.duration_in' =>   $duration_in,
                // 'exp_date'          =>   $exp_date,
            ]);

            $user_master = DB::connection('mysql2')->table('users_master_code')->where('username', $request->number)->first();
            DB::connection('mysql2')->table('mastercode_devices')->where('mastercode_devices.user_id' , $user_master->id)->update([
                'mastercode_devices.mac' => $request['mac']
            ]);


        }else {

            if(!$request->is_trial){
                if($user_type !='Admin'){
                    $sld->update([
                            
                        'solde' => $sld->solde - 1,
    
                        ]);
                }
            }
        }

        
        if($request->check === null){
        MasterCode::whereId($id)->update([
            'len'               => $request['len'],
            'name'              => $request['name'],
            'number'            => $request['number'],
            'mac'               => $request['mac'],
            'package_id'        => $request['pack'],
            'user_id'           => $user_id,
            'notes'             => $request['notes']? $request['notes'] : 'iActive',
            
        ]);

        $up = DB::table('master_codes')->where('id', $id)->first();


        if($user_type != 'Admin'){
               
                if($request->notes !== null){
                    $kk = $request->notes;
                    $var = '';
                    //  $kk=1;
                }else {
                    // $kk=0;
                    $kk = 'iActive';
                    $var = '';
                }
            }else {
                
                if($request->notes !== null){
                    $var = $request->notes;
                    $kk ='';
                    // $var=1;
                }else {
                    // $var=0;
                    $var = 'iActive';
                    $kk ='';
                }
                
            }
            
            DB::connection('mysql2')->table('users_master_code')->where('users_master_code.username' , $request['number'])->update([
                // 'users_master_code.macadress'  => $up->mac,
                'users_master_code.package_id' => $request['pack'],
                'users_master_code.username'   => $request['number'],
                'users_master_code.admin_notes' =>    $var,
                'users_master_code.reseller_notes' => $kk,
                // 'users.duration_p'  =>   $duration_p,	
                // 'users.duration_in' =>   $duration_in,
                // 'exp_date'          =>   $exp_date,
            ]);
            $user_master = DB::connection('mysql2')->table('users_master_code')->where('username', $request->number)->first();
            // $user_master = DB::connection('mysql2')->table('users')->where('users.username' , $request->number)->first();
            DB::connection('mysql2')->table('mastercode_devices')->where('mastercode_devices.user_id' , $user_master->id)->update([
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

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MasterCode::whereIn('user_id', $res)->where('id', $id)->first();
            if($is_owner) {}
            else{
                return response(['message'=>'Wrong user'], 403);
            }
        }

        $current = DB::table('master_codes')->where('number', $id)->first();
        // dd($current);

        DB::table('master_codes')->where('number', $id)->update([
            'enabled' => 0,
        ]);

        $userT = DB::connection('mysql2')->table('users_master_code')->where('username', $current->number)->first();

        return DB::connection('mysql2')->table('users_master_code')->where('id' , $userT->id)->update([
            'enabled' => 0,
        ]);

    }

    public function deleteMastercode($id, $mac, $type)
    {

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MasterCode::whereIn('user_id', $res)->where('id', $id)->first();
            if($is_owner) {}
            else{
                return response(['message'=>'Wrong user'], 403);
            }
        }

        $current = DB::table('master_codes')->where('number', $id)->first();
        // dd($current);
        if($type == 'disabled') {
            DB::table('master_codes')->where('number', $id)->update([
                'enabled' => 0,
            ]);

            $userT = DB::connection('mysql2')->table('users_master_code')->where('username', $current->number)->first();

            return DB::connection('mysql2')->table('users_master_code')->where('id' , $userT->id)->update([
                'enabled' => 0,
            ]);
        }else{
            DB::table('master_codes')->where('number', $id)->delete();

            $userT = DB::connection('mysql2')->table('users_master_code')->where('username', $current->number)->first();

            return DB::connection('mysql2')->table('users_master_code')->where('id' , $userT->id)->delete();
            return DB::connection('mysql2')->table('mastercode_devices')->where('user_id' , $userT->id)->delete();
        }

    }

    public function enableMastercode($id, $mac)
    {

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MasterCode::whereIn('user_id', $res)->where('number', $id)->first();
            if($is_owner) {}
            else{
                return response(['message'=>'Wrong user'], 403);
            }
        }

        $current = DB::table('master_codes')->where('number', $id)->where('mac', $mac)->first();
        // dd($current);

        DB::table('master_codes')->where('number', $id)->where('mac', $mac)->update([
            'enabled' => 1,
        ]);

        $userT = DB::connection('mysql2')->table('users')->where('username', $current->number)->where('macadress', $current->mac)->first();

        return DB::connection('mysql2')->table('users')->where('id' , $userT->id)->update([
            'enabled' => 1,
        ]);

    }

    public function Renew(Request $request, $code)
    {

        if(Auth::user()->type != "Admin") {
            $res = SubResiler::where( 'res_id', Auth::user()->id )->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);

            $is_owner = MasterCode::whereIn('user_id', $res)->where('number', $code)->first();
            if($is_owner) {}
            else{
                return response(['message'=>'Wrong user'], 403);
            }
        }

        // dd($code);

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $sld = User::find($user_id);

        $user_master =  DB::connection('mysql2')->table('users_master_code')->where('users_master_code.username' , $code)->first();
        if($user_type != 'Admin') {
            $count = DB::connection('mysql2')->table('mastercode_devices')->where('mastercode_devices.user_id' , $user_master->id)->count();
            if($sld->solde - $count < 0) {
                DB::rollback();
                return response()->json(['msg'=> 'solde'], 401);
            }
        }
        

        $expiredate= strtotime( "+12 month" );
        $string_expiredate = date("Y-m-d", $expiredate);

        $current =  DB::table('master_codes')->where('number', $code)->first();
        $old = $current->time; 
        $ee = new Carbon($old);
        if($ee > Carbon::now()) {}
        else{
            $old = Carbon::now();
        }
        $date   = new Carbon($old);
        $expire = $date->addDays(365);
        $exp = $expire->format('Y-m-d H:i:s');


        $now = Carbon::now();
        $length = $now->diff($exp)->days;

    

        DB::table('master_codes')->where('number', $code)->update([
            'days'  => $length.' '."days",
            'time'  =>  $exp
        ]);
            
        DB::connection('mysql2')->table('users_master_code')->where('users_master_code.username' , $code)->update(
            [               
                // 'users_master_code.duration_p'  =>   $length,	
                // 'users_master_code.duration_in' =>   'days',
                'users_master_code.exp_date'    =>   strtotime($exp),
                'is_trial'          => 0,
                'is_mag' => 0
            ]
        );        

        DB::connection('mysql2')->table('mastercode_devices')->where('mastercode_devices.user_id' , $user_master->id)->update(
            [               
                // 'users.duration_p'  =>   $length,	
                // 'users.duration_in' =>   'days',
                'mastercode_devices.exp_date_master'    =>   strtotime($exp),
            ]
        );

       $count = DB::connection('mysql2')->table('mastercode_devices')->where('mastercode_devices.user_id' , $user_master->id)->count();
    

        if($user_type !='Admin'){
            $sld->update([                    
                'solde' => $sld->solde - $count
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

            $is_owner = MasterCode::whereIn('user_id', $res)->where('id', $id)->first();
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
                $current =  DB::table('master_codes')->find($id);
                $old = $current->time; 

                $new_date = explode(" ", $request->days);

                $date   = Carbon::now();
                $expire = $date->addDays($new_date[0]);
                $exp = $expire->format('Y-m-d H:i:s');

                $now = Carbon::now();
                $length = $now->diff($exp)->days +1;

                DB::table('master_codes')->whereId($id)->update([
                    'days'  => $length.' '."days",
                    'time'  =>  $exp,
                ]);
                    

                DB::connection('mysql2')->table('users_master_code')->where('users_master_code.username' , $current->number)->update(
                    [
                        'users_master_code.exp_date'    =>   strtotime($exp),
                        'is_mag' => 0
                    ]

                );
                $user_master =  DB::connection('mysql2')->table('users_master_code')->where('users_master_code.username' , $current->number)->first();
                DB::connection('mysql2')->table('mastercode_devices')->where('mastercode_devices.user_id' , $user_master->id)->update(
                    [               
                        'mastercode_devices.exp_date_master'    =>   strtotime($exp),
                    ]
                );
    }

}
