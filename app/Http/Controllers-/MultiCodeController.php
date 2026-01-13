<?php

namespace App\Http\Controllers;

use App\MultiCode;
use App\User;
use App\ResellerStatistic;
use App\SubResiler;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use DB;
use Illuminate\Validation\Rule;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

use PDF;


class MultiCodeController extends Controller
{


    public function pdf(Request $request, $codes, $duree)
    {
        $codes = explode(",", $codes);
        $date = date("d/m/Y");

        $pdf = \PDF::loadView('masscodes',['codes' =>$codes, 'date'=>$date, 'duree'=>$duree]);

        $pdf->save(storage_path().'_filename.pdf');

        return $pdf->download('MassCodes.pdf');
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
        $subRes = SubResiler::where('res_id', $user_id)->get();
        $subArray = [];
        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }
        array_push($subArray, $user_id);
        if($user_type !== 'Admin'){
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.user_id', $subArray);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            if($query) $MultiCode = $MultiCode->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            if($query) $MultiCode = $MultiCode->where('multi_codes.number','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $userT->days = $new_days->format("%a days");
                        }else{
                            $userT->days = "0 days";
                        }
                    }
                }
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
            }
        }
        
        return Response()->json($MultiCode);
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
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $user_solde = Auth::user()->solde;

        if($user_type != 'Admin'){
            if($request->max > $user_solde){
                $max = $user_solde;
            }else {
                $max = $request->max;
            }
        }else {

            $max = $request->max;
        }
        if($request->pack == 185) {
            $len = $request->len - 6;
            $start = "185001";
        }else{
            $len = $request->len - 3;
            $start = $request->NumStart;
        }
        
        $array= [];
        for ($x = 0; $x < $max; $x++) {
            $random_number = (string) mt_rand(0, PHP_INT_MAX);
            $shuffled_number = str_shuffle ($random_number);
            $randome_code = substr($shuffled_number, 0, $len);
            $rand = $start.$randome_code;
            if(MultiCode::where('number', $rand)->first()){
                $random_number = (string) mt_rand(0, PHP_INT_MAX);
                $shuffled_number = str_shuffle ($random_number);
                $randome_code = substr($shuffled_number, 0, 2);
                $rand = $start.$randome_code;
            }else {
                if( in_array( $rand ,$array ) )
                {
                    $rand = substr($shuffled_number, 0, 2);
                }else {
                    $array[] = $rand;
                }                
            }
        }

        while(count($array) <$max){
            $count = count($array);
            $az = $max - $count;
            for ($x = 0; $x < $az; $x++) {
                $random_number = (string) mt_rand(0, PHP_INT_MAX);
                $shuffled_number = str_shuffle ($random_number);
                $randome_code = substr($shuffled_number, 0, $len);
                $rand = $start.$randome_code;
                if(MultiCode::where('number', $rand)->first()){
                    $random_number = (string) mt_rand(0, PHP_INT_MAX);
                    $shuffled_number = str_shuffle ($random_number);
                    $randome_code = substr($shuffled_number, 0, 2);
                    $rand = $start.$randome_code;
                }else {
                    if( in_array( $rand ,$array ) )
                    {
                        $rand = substr($shuffled_number, 0, 2);
                    }else {
                        $array[] = $rand;
                    }
                }
            }
        }
        sort($array);
        $sizearray = array_values($array);
        return Response()->json($sizearray);
    }
  
    public function SendMassCode()
    {
        request()->validate([
            'name' => 'required',
            'len' => 'required',
            'pack' => 'required',
            'hh'   => 'required',
            'myArray'   => 'max:2000',
        ]);
    }

    public function store(Request $request)
    {
        request()->validate([
            'name' => 'required',
            'len' => 'required',
            'pack' => 'required',
            'hh'   => 'required',
            'myArray'   => 'max:2000',
        ]);

        DB::beginTransaction();
        try {   
            
            $user = Auth::user();
            $user_id = auth()->id();
            $user_type = Auth::user()->type;
            $user_solde = Auth::user()->solde;
            $array = explode("\n", $request->hh);

            $sld = User::find($user_id);

            $now = date("Y-m-d H:i:s");
            $now = strtotime( "$now" );
            $created_at = $now;

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
                }else if($duration_p == '366' && $duration_in =='days'){
                    $duration_p = '366' ;
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

            $i = 0;

            foreach($array as $m){
                MultiCode::create([
                    'len'               => $request['len'],
                    'name'              => $request['name'],
                    'number'            => $array[$i],
                    'pack'              => $request['pack'],
                    'days'              => $duration_p . ' ' .$duration_in,
                    'max'               => $request['max'],
                    'user_id'           => $user_id,
                    'package_id'        => $request['pack'],
                    'notes'             => $request['notes']? $request['notes'] : 'iActive',
                    'pack'              => json_encode($request['pack_list']),
                ]);

                DB::connection('mysql2')->table('users_activecode')->insert(
                [
                    'member_id'   =>   $user_id,
                    'created_by'  =>   $user_id,
                    'username'    =>   $array[$i],
                    'password'    =>   $request->password,
                    'admin_notes' =>    $kk,
                    'reseller_notes' => $var,
                    'package_id'  =>   $request->pack,
                    'duration_p'  =>   $duration_p, 
                    'duration_in' =>   $duration_in,
                    'bouquet'     =>   $request->bouquets,
                    'is_trial'    =>   $request->is_trial,
                    'allowed_ips' =>   '',
                    'allowed_ua'  =>   '',
                    'created_at'  =>   $created_at,
                    'typecode'    =>   2,
                    'exp_date'    => 0,
                    'forced_country' => "",
                    "access_output_id" => "[1,2,3]",
                    'play_token'=>""

                ]);

                $id = DB::connection('mysql2')->table('users')->orderBy('users.id', 'desc')->first()->id;
                
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
                            'operation_name' => 'mass_code',
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

                $i++;
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
     * @param  \App\MultiCode  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function show(ActiveCode $activeCode)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\MultiCode  $activeCode
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
     * @param  \App\MultiCode  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $multi =  MultiCode::whereId($id)->first();        
        request()->validate([
            'number' => Rule::unique('multi_codes')->ignore($multi->number, 'number'),
            'name' => 'required'            
        ]);
            
        $current = DB::table('multi_codes')->where('id', $id)->first();

        $xtream = DB::connection('mysql2')->table('users_activecode')->where('username' , $current->number)->first();
        if($xtream) {}
        else{
            $xtream = DB::connection('mysql2')->table('users')->where('username' , $current->number)->first();
        }
        
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
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

            $days = $duration_p . ' ' .$duration_in;

            $time = Null;

            $exp_date =  Null;
            $mac = '';

        }else {
            $days = $current->days;
            $time = $current->time;
            $duration_p = $xtream->duration_p;
            $duration_in = $xtream->duration_in;
            $exp_date =  $xtream->exp_date;
        }

        MultiCode::whereId($id)->update([
            'len'               => $request['len'],
            'name'              => $request['name'],
            'number'            => $request['number'] ,
            'package_id'        => $request['pack'],
            'days'              => $days,
            'user_id'           => $user_id,
            'notes'             => $request['notes']? $request['notes'] : 'iActive',
            'pack'        => json_encode($request['pack_list']),
        ]);

        
         if($user_type != 'Admin'){
               
                if($request->notes !== null){
                    $kk = $request->notes;
                    $var = '';
                }else {
                    $request->notes = 'iActive';
                    $var = '';
                }
            }else {                
                if($request->notes !== null){
                    $var = $request->notes;
                    $kk ='';
                }else {
                    $request->notes = 'iActive';
                    $kk ='';
                }                
            }
            $check_usact = DB::connection('mysql2')->table('users')->where('username' , $current->number)->first();
            if($check_usact) {
                DB::connection('mysql2')->table('users')->where('username' , $current->number)
                ->update([
                    'macadress'  => $request['mac'],
                    'package_id' => $request['pack'],
                    'username'   => $request['number'],
                    'admin_notes' =>    $kk,
                    'reseller_notes' => $var,
                    'duration_p'  =>   $duration_p,	
                    'duration_in' =>   $duration_in,
                    'bouquet'     =>   $request->bouquets,
                    'is_trial'    =>   $request->is_trial
                ]);
            }else {
                DB::connection('mysql2')->table('users_activecode')->where('username' , $current->number)
                ->update([
                    'macadress'  => $request['mac'],
                    'package_id' => $request['pack'],
                    'username'   => $request['number'],
                    'admin_notes' =>    $kk,
                    'reseller_notes' => $var,
                    'duration_p'  =>   $duration_p,	
                    'duration_in' =>   $duration_in,
                    'bouquet'     =>   $request->bouquets,
                    'is_trial'    =>   $request->is_trial
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
        $user_type = Auth::user()->type;
        $curr = DB::table('multi_codes')->where('id', $id)->first();
        if($type == 'delete') {
            MultiCode::whereId($id)->delete();
            DB::connection('mysql2')->table('users')->where('username' , $curr->number)->delete();
            $get_user =  DB::connection('mysql2')->table('users')->where('username' , $curr->number)->first();
            if($get_user) {
                return DB::connection('mysql2')->table('users')->where('username' , $curr->number)->update(
                    [
                        'enabled' => 0,
                    ]
                );
            }else{
                return DB::connection('mysql2')->table('users_activecode')->where('username' , $curr->number)->update(
                    [
                        'enabled' => 0,
                    ]
                );
            }
        }else{
            MultiCode::whereId($id)->update([
                'enabled' => 0 ,
                
            ]);
            $get_user =  DB::connection('mysql2')->table('users')->where('username' , $curr->number)->first();
            if($get_user) {
                return DB::connection('mysql2')->table('users')->where('username' , $curr->number)->update(
                    [
                        'enabled' => 0,
                    ]
                );
            }else{
                return DB::connection('mysql2')->table('users_activecode')->where('username' , $curr->number)->update(
                    [
                        'enabled' => 0,
                    ]
                );
            }
        }

    }

    public function enableMassCodeD($number)
    {
        MultiCode::where('number', $number)->update([
            'enabled' => 1 ,
            
        ]);
        $get_user =  DB::connection('mysql2')->table('users')->where('username' , $number)->first();
        if($get_user) {
            return DB::connection('mysql2')->table('users')->where('username' , $number)->update(
                [
                    'enabled' => 1,
                ]
            );
        }else{
            return DB::connection('mysql2')->table('users_activecode')->where('username' , $number)->update(
                [
                    'enabled' => 1,
                ]
            );
        }
    }

    public function resetMac($id){

        $current = DB::table('multi_codes')->where('id', $id)->first();
        MultiCode::whereId($id)->update(['mac' => '']);
        DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->update(['users.macadress' => 'Mac Reseted']);
    }

    public function Renew(Request $request, $code)
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
        $getM = DB::table('multi_codes')->where('number', $code)->first();
        $pack_id = $getM->package_id;
        $getP = DB::connection('mysql2')->table('packages')->select('packages.*')
            ->where('packages.id' , $pack_id)->first();
        if($getP->is_trial == 1) {
            foreach ($dd as $pack) {
                if($pack->official_duration == '365' || $pack->official_duration == '366') {
                    $pack_id = $pack->id;
                }
            }
        }
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $sld = User::find($user_id);
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
        }

        $current =   DB::table('multi_codes')->where('number', $code)->first();

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

        $expiredate= strtotime( "+12 month" );
        $string_expiredate = date("Y-m-d", $expiredate);

        $current =  DB::table('multi_codes')->where('number', $code)->first();
        $old = $current->time; 
        $ee = new Carbon($old);
        if($ee > Carbon::now()) {}
        else{
            $old = Carbon::now();
        }
        $date   = new Carbon($old);
        $expire = $date->addDays($addDays);
        $exp = $expire->format('Y-m-d H:i:s');

        $now = Carbon::now();
        $length = $now->diff($exp)->days;
        
        DB::table('multi_codes')->where('number', $code)->update([
            'days'  =>  $length.' '."days",
            'time'  =>  $exp,
            'package_id' => $pack_id 
        ]);
            

        DB::connection('mysql2')->table('users')->where('users.username' , $code)->update(
            [
                
                'users.duration_p'  =>   '365',	
                'users.duration_in' =>   'days',
                'users.exp_date'    =>   strtotime($exp),
                'is_trial'          => 0,
                'package_id' => $pack_id,
                'is_mag' => 0
                

            ]

       );

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
        $user->gift = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $user->gift);
        $real_solde = (float)$user->solde + (float)$user->gift;
        if($user->type != 'Admin') { return response()->json(['solde'=>$real_solde, 'solde_test'=>floatval($user->solde_test)]); }
        else { return response()->json(['solde'=>1000]); }        
    }

    public function changeDays(Request $request, $id)
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $user_solde = Auth::user()->solde;

        $sld = User::find($user_id);
        $current =  DB::table('multi_codes')->find($id);
        $old = $current->time; 

        $new_date = explode(" ", $request->days);

        $date   = Carbon::now();
        $expire = $date->addDays($new_date[0]);
        $exp = $expire->format('Y-m-d H:i:s');

        $now = Carbon::now();
        $length = $now->diff($exp)->days +1;

        DB::table('multi_codes')->whereId($id)->update([
            'days'  => $length.' '."days",
            'time'  =>  $exp,
        ]);

        $user_ex = DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->first();
        if($user_ex) {
            DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->update(
                [
                    'users.duration_p'  =>    $length,	
                    'users.duration_in' =>   'days',
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

    public function byReseller(Request $req, $resID)
    {        
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        if($user_type !== 'Admin'){

            $model = new MultiCode;
            $MultiCode =  $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);           

        }else {
            $model = new MultiCode;
            $MultiCode =  $model;            

            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")
                ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_activecode) { $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            elseif( $user_users){ $active->selected_bouquets = json_decode($user_users->bouquet); }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($userT) {
                $active->is_trial = $userT->is_trial;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }
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
            }
        }        
        return Response()->json($MultiCode);
    }

    public function expiredItems()  {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $query = request('query');
        $subRes = SubResiler::where('res_id', $user_id)->get();

        $users = DB::connection('mysql2')->table('users')->where('exp_date', '<', strtotime(date("Y/m/d H:i:s")))->pluck('username');

        $subArray = [];
        foreach ($subRes as  $row) {
            array_push($subArray, $row->user_id);
        }
        array_push($subArray, $user_id);
        if($user_type !== 'Admin'){
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.user_id', $subArray);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if($query) $MultiCode = $MultiCode->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('number', $users);
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            if($query) $MultiCode = $MultiCode->where('multi_codes.number','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $userT->days = $new_days->format("%a days");
                        }else{
                            $userT->days = "0 days";
                        }
                    }
                }
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
            }
        }
        
        return Response()->json($MultiCode);
    }

    public function expiredItemsByUser(Request $req, $resID)  {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $users = DB::connection('mysql2')->table('users')->where('exp_date', '<', strtotime(date("Y/m/d H:i:s")))->pluck('username');

        $query = request('query');

        if($user_type !== 'Admin'){

            $model = new MultiCode;
            $MultiCode =  $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);           

        }else {
            $model = new MultiCode;
            $MultiCode =  $model;            

            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")
                ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_activecode) { $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            elseif( $user_users){ $active->selected_bouquets = json_decode($user_users->bouquet); }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($userT) {
                $active->is_trial = $userT->is_trial;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }
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
            }
        }        
        return Response()->json($MultiCode);
    }

    public function onlineItems()  {
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

        $users_activity_now_pluck = DB::connection('mysql2')->table('user_activity_now')->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->pluck('user_id');
        $users = DB::connection('mysql2')->table('users')->whereIn('id', $users_activity_now_pluck)->pluck('username');

        if($user_type !== 'Admin'){
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.user_id', $subArray);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if($query) $MultiCode = $MultiCode->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('number', $users);
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            if($query) $MultiCode = $MultiCode->where('multi_codes.number','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $userT->days = $new_days->format("%a days");
                        }else{
                            $userT->days = "0 days";
                        }
                    }
                }
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
            }
        }
        
        return Response()->json($MultiCode);
    }

    public function onlineItemsByUser(Request $req, $resID)  {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        $users_activity_now_pluck = DB::connection('mysql2')->table('user_activity_now')->select(DB::raw('count(activity_id) as count, user_id'))->groupBy('user_id')->pluck('user_id');
        $users = DB::connection('mysql2')->table('users')->whereIn('id', $users_activity_now_pluck)->pluck('username');

        if($user_type !== 'Admin'){

            $model = new MultiCode;
            $MultiCode =  $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);           

        }else {
            $model = new MultiCode;
            $MultiCode =  $model;            

            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")
                ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_activecode) { $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            elseif( $user_users){ $active->selected_bouquets = json_decode($user_users->bouquet); }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($userT) {
                $active->is_trial = $userT->is_trial;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }
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
            }
        }        
        return Response()->json($MultiCode);
    }

    public function amolstExpiredItems()  {
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
            ->pluck('username');

        if($user_type !== 'Admin'){
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.user_id', $subArray);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if($query) $MultiCode = $MultiCode->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if($query) $MultiCode = $MultiCode->where('multi_codes.number','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $userT->days = $new_days->format("%a days");
                        }else{
                            $userT->days = "0 days";
                        }
                    }
                }
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
            }
        }
        
        return Response()->json($MultiCode);
    }

    public function amolstExpiredItemsByUser(Request $req, $resID)  {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        $users = DB::connection('mysql2')->table('users')
            ->where('exp_date', '<=', strtotime(Date('Y/m/d H:i:s', strtotime("+2 days"))))
            ->where('exp_date', '>', strtotime(date("Y/m/d H:i:s")))
            ->pluck('username');

        if($user_type !== 'Admin'){

            $model = new MultiCode;
            $MultiCode =  $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);           

        }else {
            $model = new MultiCode;
            $MultiCode =  $model;            

            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->whereIn('number', $users);
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")
                ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_activecode) { $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            elseif( $user_users){ $active->selected_bouquets = json_decode($user_users->bouquet); }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($userT) {
                $active->is_trial = $userT->is_trial;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }
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
            }
        }        
        return Response()->json($MultiCode);
    }

    public function trialItems()  {
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
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.user_id', $subArray);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->whereIn('multi_codes.package_id', $packages);
            if($query) $MultiCode = $MultiCode->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.package_id', $packages);
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            if($query) $MultiCode = $MultiCode->where('multi_codes.number','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $userT->days = $new_days->format("%a days");
                        }else{
                            $userT->days = "0 days";
                        }
                    }
                }
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
            }
        }
        
        return Response()->json($MultiCode);
    }

    public function trialItemsByUser(Request $req, $resID)  {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        $packages = DB::connection('mysql2')->table('packages')->where('is_trial', 1)->pluck('id');

        if($user_type !== 'Admin'){

            $model = new MultiCode;
            $MultiCode =  $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->whereIn('multi_codes.package_id', $packages);
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);           

        }else {
            $model = new MultiCode;
            $MultiCode =  $model;            

            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.package_id', $packages);
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")
                ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_activecode) { $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            elseif( $user_users){ $active->selected_bouquets = json_decode($user_users->bouquet); }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($userT) {
                $active->is_trial = $userT->is_trial;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }
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
            }
        }        
        return Response()->json($MultiCode);
    }

    public function disabledItems()  {
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
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.user_id', $subArray);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->where('enabled', 0);
            if($query) $MultiCode = $MultiCode->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->where('enabled', 0);
            if($query) $MultiCode = $MultiCode->where('multi_codes.number','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $userT->days = $new_days->format("%a days");
                        }else{
                            $userT->days = "0 days";
                        }
                    }
                }
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
            }
        }
        
        return Response()->json($MultiCode);
    }

    public function disabledItemsByUser(Request $req, $resID)  {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        if($user_type !== 'Admin'){

            $model = new MultiCode;
            $MultiCode =  $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->where('enabled', 0);
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);           

        }else {
            $model = new MultiCode;
            $MultiCode =  $model;            

            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->where('enabled', 0);
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")
                ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_activecode) { $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            elseif( $user_users){ $active->selected_bouquets = json_decode($user_users->bouquet); }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($userT) {
                $active->is_trial = $userT->is_trial;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }
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
            }
        }        
        return Response()->json($MultiCode);
    }

    public function enabledItems()  {
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
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->whereIn('multi_codes.user_id', $subArray);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->where('enabled', 1);
            if($query) $MultiCode = $MultiCode->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }else {
            $model = new MultiCode;
            $MultiCode =  $model;
            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->where('enabled', 1);
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            if($query) $MultiCode = $MultiCode->where('multi_codes.number','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                                                        ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);
        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_users) { $active->selected_bouquets = json_decode($user_users->bouquet); }
            elseif( $user_activecode){ $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            if($active->pack) {
                $active->pack = json_decode($active->pack);
            }else{
                $active->pack = '';
            }
            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            $active->has_is_trial = false;
            if($userT) {
                $active->is_trial = $userT->is_trial;
                $active->has_is_trial = true;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                        $date_today = date_create(date('Y-m-d H:i:s'));
                        $exp_time = date_create(date("Y-m-d H:i:s", $userT->exp_date));
                        $new_days = date_diff($date_today,$exp_time);
                        // $active->days = $new_days->format("%a days");
                        if(date("Y-m-d", $userT->exp_date) > date('Y-m-d')) {
                            $userT->days = $new_days->format("%a days");
                        }else{
                            $userT->days = "0 days";
                        }
                    }
                }
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
            }
        }
        
        return Response()->json($MultiCode);
    }

    public function enabledItemsByUser(Request $req, $resID)  {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');

        if($user_type !== 'Admin'){

            $model = new MultiCode;
            $MultiCode =  $model;

            $MultiCode = $MultiCode->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('multi_codes.deleted_at','=' , Null);
            $MultiCode = $MultiCode->where('enabled', 1);
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);           

        }else {
            $model = new MultiCode;
            $MultiCode =  $model;            

            $MultiCode = $MultiCode ->join('users', 'multi_codes.user_id', '=', 'users.id');
            $MultiCode = $MultiCode->where('multi_codes.user_id', $resID);
            $MultiCode = $MultiCode->where('enabled', 1);
            $MultiCode = $MultiCode ->with('user')->select('multi_codes.*','users.name AS UserName');
            $MultiCode = $MultiCode ->where('multi_codes.deleted_at','=' , Null);
            
            if($query) $MultiCode = $MultiCode->where('multi_codes.user_id', $resID)->where(function ($q) use ($query) {
                return $q->where('multi_codes.number','LIKE', "%{$query}%")
                ->orWhere('multi_codes.mac','LIKE', "%{$query}%")
                ->orWhere('multi_codes.name','LIKE', "%{$query}%")
                ->orWhere('multi_codes.notes','LIKE', "%{$query}%");
            });

            $MultiCode = $MultiCode->orderBy('id', 'desc')->paginate(20);

        }

        foreach ($MultiCode as $active) {
            $active->last_connection = 'NEVER';
            $active->flag = '';
            $active->user_ip = '-';
            $active->stream_id = '';
            $active->stream_name = '';
            $active->online = 0;$active->latency= 0;
            $active->selected_bouquets = [];
            $user_activecode = DB::connection('mysql2')->table('users_activecode')->where('username', $active->number)->first();
            $user_users = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();

            $active->user = User::find($active->user_id);
            
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $active->package_id)->first();
            
            if($user_activecode) { $active->selected_bouquets = json_decode($user_activecode->bouquet); }
            elseif( $user_users){ $active->selected_bouquets = json_decode($user_users->bouquet); }

            $userT = DB::connection('mysql2')->table('users')->where('username', $active->number)->first();
            if($userT) {
                $active->is_trial = $userT->is_trial;
                if($active->mac == "" || $active->mac == null) {
                    if($userT->macadress != "" || $userT->macadress != null){
                        $active->mac = str_replace(":", "", $userT->macadress);
                    } 
                    if($userT->exp_date != "" || $userT->exp_date != null){
                        $active->time =  date("Y-m-d H:i:s", $userT->exp_date);
                    }
                }
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
            }
        }        
        return Response()->json($MultiCode);
    }

}
