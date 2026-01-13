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
        // $MasterCode = MasterCode::all();
        // return Response()->json($MasterCode);

        // $MasterCode =  MasterCode::latest()->paginate(10);
        // return json_encode($MasterCode);

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $query = request('query');
        // $kk = request('query');
        // return MasterCode::find($user_id)->latest()->paginate(10);
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
            
            
            $MasterCode = $MasterCode->orderBy('id', 'ASC')->paginate(20);

            

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

            $MasterCode = $MasterCode->orderBy('id', 'ASC')->paginate(20);

        }

        foreach ($MasterCode as $master) {
            $check_trial = DB::connection('mysql2')->table('packages')->where('id', $master->package_id)->first();
            $master->is_trial = $check_trial->is_trial;
            $userT = DB::connection('mysql2')->table('users')->where('username', $master->number)->where('macadress', $master->mac)->first();
            $master->user_id_x = $userT->id;

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
        ]);

        DB::beginTransaction();
        try {           
        
            
            // dd($request->mac);

            // $mac = "ssssss\ndddddd\nttttttt\nyyyyyyyyyyyyy";

            $data = explode("\n", $request->mac);

            $array_mac = str_replace(":" , "", $data);
            // dd($array_mac);


        
            // dd($i);

            
            $now = date("Y-m-d H:i:s");
            $now = strtotime( "$now" );
            $created_at = $now;

            $user = Auth::user();
            $user_id = auth()->id();
            $user_type = Auth::user()->type;

            // dd($user->id);
            //    dd($request->all());
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
                
            foreach($array_mac as $m){

            
                            MasterCode::create([
                                'len'               => $request['len'],
                                'name'              => $request['name'],
                                'number'            => $request['number'],
                                'mac'               => $array_mac[$i],
                                'days'              => $duration_p . ' ' .$duration_in,
                                'user_id'           => $user_id,
                                'notes'             => $request['notes']? $request['notes'] : 'iActive',
                                'package_id'        => $request['pack'],

                                
                            ]);

                            // if($user_type != 'Admin'){
                            //     $var=  0;
                            // }else {
                            //     $var=  1;
                            // }

                            DB::connection('mysql2')->table('users')->insert(
                                [
                                    'member_id'   =>   $user_id,
                                    'created_by'  =>   $user_id,
                                    'username'    =>   $request->number,
                                    'password'    =>   $request->password,
                                    'macadress'    =>   $array_mac[$i],
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
                                    'typecode'    =>   1,
                                    

                                ]);


                            $id = DB::connection('mysql2')->table('users')->orderBy('users.id', 'desc')->first()->id;
                            
                                // dd($id);
                                

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
                                    $sld->update([
                                            
                                        'solde' => $sld->solde - 1
                        
                                        ]);
                                    }
                    $i++;
                }//end foreach
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
        // dd($xtream);
        
        
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

                    

                    
        }else {

            $days = $current->days;
            $time = $current->time;

            $duration_p = $xtream->duration_p;
            $duration_in = $xtream->duration_in;

            $exp_date =  $xtream->exp_date;

        }

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
                'days'              => $days,
                'time'              => $time,
                'user_id'           => $user_id,
                'notes'             => $request['notes']? $request['notes'] : 'iActive',
            ]);

            

            DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->update([
                'users.package_id' => $request['pack'],
                'users.username'   => $request['number'],
                'users.admin_notes' =>    $var,
                'users.reseller_notes' => $kk,
                'users.duration_p'  =>   $duration_p,	
                'users.duration_in' =>   $duration_in,
                'exp_date'          =>   $exp_date,
            ]);


            $cnt = DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->count();

            if(!$request->is_trial){
                if($user_type !='Admin'){
                    $sld->update([
                            
                        'solde' => $sld->solde - $cnt
    
                        ]);
                }
            }

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
            'days'              => $days,
            'time'              => $time,
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

        DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->where('users.macadress', $current->mac)
            ->update([
                'users.macadress'  => $up->mac,
                'users.package_id' => $request['pack'],
                'users.username'   => $request['number'],
                   'users.admin_notes' =>    $var,
                'users.reseller_notes' => $kk,
                'users.duration_p'  =>   $duration_p,	
                'users.duration_in' =>   $duration_in,
                'exp_date'          =>   $exp_date,

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

        $current = DB::table('master_codes')->where('number', $id)->where('mac', $mac)->first();
        // dd($current);

        DB::table('master_codes')->where('number', $id)->update([
            'enabled' => 0,
        ]);

        $userT = DB::connection('mysql2')->table('users')->where('username', $current->number)->where('macadress', $current->mac)->first();

        return DB::connection('mysql2')->table('users')->where('id' , $userT->id)->update([
            'enabled' => 0,
        ]);

    }

    public function deleteMastercode($id, $mac, $type)
    {

        $current = DB::table('master_codes')->where('number', $id)->where('mac', $mac)->first();
        // dd($current);
        if($type == 'disabled') {
            DB::table('master_codes')->where('number', $id)->where('mac', $mac)->update([
                'enabled' => 0,
            ]);

            $userT = DB::connection('mysql2')->table('users')->where('username', $current->number)->where('macadress', $current->mac)->first();

            return DB::connection('mysql2')->table('users')->where('id' , $userT->id)->update([
                'enabled' => 0,
            ]);
        }else{
            DB::table('master_codes')->where('number', $id)->where('mac', $mac)->delete();

            $userT = DB::connection('mysql2')->table('users')->where('username', $current->number)->where('macadress', $current->mac)->first();

            return DB::connection('mysql2')->table('users')->where('id' , $userT->id)->delete();
        }

    }

    public function enableMastercode($id, $mac)
    {

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

    // public function resetMac($id){

    //     $current = DB::table('master_codes')->where('id', $id)->first();


    //     MasterCode::whereId($id)->update(['mac' => '']);

    //     // DB::connection('mysql2')->table('users')
    //     // ->where('users.username' , $current->number)
    //     // ->where('users.macadress' , $current->mac)
    //     // ->update(['users.macadress' => '']);
    // }

    public function Renew(Request $request, $code)
    {

        // dd($code);

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $sld = User::find($user_id);

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
            
        DB::connection('mysql2')->table('users')->where('users.username' , $code)->update(
            [
                
                'users.duration_p'  =>   $length,	
                'users.duration_in' =>   'days',
                'users.exp_date'    =>   strtotime($exp),
                'is_trial'          => 0,
                'is_mag' => 0

            ]

       );

       $count = DB::connection('mysql2')->table('users')->where('users.username' , $code)->count();
    //    dd($count);

       if($user_type !='Admin'){
        //    for($i=0 ;$i <$count ; $i++){
                $sld->update([
                        
                    'solde' => $sld->solde - $count

                ]);
        // }
        
     }



    }


    public function changeDays(Request $request, $id)
    {
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
                    

                DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->update(
                    [
                        'users.duration_p'  =>    $length,	
                        'users.duration_in' =>   'days',
                        'users.exp_date'    =>   strtotime($exp),
                        'is_mag' => 0
                    ]

                );
    }

}
