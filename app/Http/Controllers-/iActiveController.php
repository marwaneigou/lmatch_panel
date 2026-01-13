<?php

namespace App\Http\Controllers;

use App\ActiveCode;
use App\MultiCode;
use App\MasterCode;
use App\User;
use App\ResellerStatistic;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

use DB;

use Illuminate\Http\Request;

class iActiveController extends Controller
{
    public function index()
    {
        return view('iactive');
    }

    public function index02()
    {
        return view('active02');
    }

    public function acivecodes()
    {
        $users = DB::connection('mysql2')->table('users')->select('username')->get()->toArray();
        $users = json_encode($users, true);
        $users = json_decode($users, true);
        $user_table = [];
        foreach ($users as $user) {
            array_push($user_table, $user['username']);
        }
        $activecodes = ActiveCode::whereNotIn('number', $users)->orderBy('id', 'desc')->paginate(20);
        return response()->json($activecodes);
    }

    public function activate(Request $request){


        $code=  $request["a"]; 
        $m=  $request["m"]; 
        $mac = strtolower($m);
        $kk="0";
        $tt="";
        
        $dd = ActiveCode::where('number' , $code )->first();
        $dd = ActiveCode::where('number' , $code )->first();
        if(!$dd) {
            $dd = MasterCode::where('number' , $code )->where('mac' , $mac)->first();   
        }else if(!$dd) {
            $dd = MultiCode::where('number' , $code )->first();
        }
        $master = MasterCode::where('number' , $code )->where('mac' , $mac)->first();
        $masscode = MultiCode::where('number' , $code )->first();
        $today = strtotime('today UTC 00:00');

        $mac_reseted = DB::connection('mysql2')->table('users')->select('users.*')
        ->where('username' , $code)
        ->first();
        if($mac_reseted) {
            if($mac_reseted->macadress == 'Mac Reseted') {
                $current = DB::table('active_codes')->where('id', $dd->id)->first();

                ActiveCode::whereId($dd->id)->update(['mac' => $mac]);

                DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->update(['users.macadress' => $mac]);
            }
        }

        $mm = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')
        ->where('username' , $code)
        ->get();

        if($mm->isNotEmpty()){
            foreach($mm as $cn){
                if($cn->typecode === "2"){
                    $cc = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')
                    ->where('username' , $code)
                    ->first();
                }
                else if($cn->typecode === "3"){
                    $cc = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')
                    ->where('username' , $code)
                    ->first();
                    
                }else if($cn->typecode === "1"){
                    $cc = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')
                    ->where('username' , $code)
                    ->first();
                }
            }
        }else{
                $account = '0';
                $status = '550';
                $message = 'Error. Invalid Account';
                $package_id = '01';
                $exp = '0';
        }

        
        if($mm->isNotEmpty() && $cc != null){


    
            $now = date("Y-m-d H:i:s");
            $now = strtotime( "$now" );
            $enabled_at = $now;
            $string_enabled_at = date("Y-m-d", $enabled_at);
            $enabled = $cc->enabled;
            $duration_p = $cc->duration_p;
            $duration_in = $cc->duration_in;
            $bouquet = $cc->bouquet;

            $dur = $cc->duration_p;
        
       
           if($dur == '1'){
                    $expiredate= strtotime( "+1 days" );
                    $string_expiredate = date("Y-m-d H:i:s", $expiredate);
            }else if($dur == '10'){
                    $expiredate= strtotime( "+10 days" );
                    $string_expiredate = date("Y-m-d H:i:s", $expiredate);
            }else if($dur == '30'){
                    $expiredate= strtotime( "+1 month" );
                    $string_expiredate = date("Y-m-d H:i:s", $expiredate);

            }else if($dur == '60'){
                    $expiredate= strtotime( "+2 month" );
                    $string_expiredate = date("Y-m-d H:i:s", $expiredate);
            }else if($dur == '90'){
                    $expiredate= strtotime( "+3 month" );
                    $string_expiredate = date("Y-m-d H:i:s", $expiredate);

            }else if($dur == '180'){
                    $expiredate= strtotime( "6 month" );
                    $string_expiredate = date("Y-m-d H:i:s", $expiredate);

            }else if($dur == '365' || $dur=='366'){
                    $expiredate= strtotime( "+12 month" );
                    $string_expiredate = date("Y-m-d H:i:s", $expiredate);
            }
            
            $exp  = $string_expiredate;

            $if_expired = DB::connection('mysql2')->table('users_activecode')->where('username' , $code)->first();
            if($if_expired) {
                if ($if_expired->macadress != "" && time() > $if_expired->exp_date) {
                    $status = '550';
                    $message = 'Erreur : Compte expiré';
                    $account = '0';
                    $package_id = '01';
                    $exp = '0';

                    return Response()->json([
                        'account' => $account,
                        'status' => $status,
                        'message' => $message,
                        'package_id' => $package_id,
                        'exp'        => $exp,
                    ]);
                }
            }
            

                if($cc->typecode === "2" && $cc->exp_date === Null){
                    $msg = "now";
                    
                    DB::connection('mysql2')->table('users_activecode')->where('username' , $code)
                        ->update([
                            'macadress'  => $mac,
                            'exp_date'   => $expiredate,
                            'created_at' => $enabled_at,
                    ]);
                    $user_exist = DB::connection('mysql2')->table('users')->where('username' , $code)->count();
                    if($user_exist === 0) {
                        $user_activecode = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')->where('username' , $code )->first();
                        DB::connection('mysql2')->table('users')->insert(
                        [
                            'member_id'   =>   $user_activecode->member_id,
                            'created_by'  =>   $user_activecode->created_by,
                            'username'    =>   $user_activecode->username,
                            'password'    =>   $user_activecode->password,
                            'admin_notes' =>    $user_activecode->admin_notes,
                            'reseller_notes' => $user_activecode->reseller_notes,
                            'package_id'  =>   $user_activecode->package_id,
                            'duration_p'  =>   $user_activecode->duration_p, 
                            'duration_in' =>   $user_activecode->duration_in,
                            'bouquet'     =>   $user_activecode->bouquet,
                            'is_trial'    =>   $user_activecode->is_trial,
                            'allowed_ips' =>   '',
                            'allowed_ua'  =>   '',
                            'created_at'  =>   $enabled_at,
                            'typecode'    =>   2,
                            'exp_date'    => $expiredate,
                            'macadress'  => $mac,

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
                        
                        DB::connection('mysql2')->table('user_output')->insert(
                        [
                            'user_id'   =>     $id,
                            'access_output_id'  =>   3,
                            
                        ]);
                    }
                        if($dd != null){    
                            $dd->update([
                                'mac'  => $mac,
                                'time' => $string_expiredate,

                                ]);

                            }

                }else if($cc->typecode === "2" && $cc->exp_date !== Null && $cc->macadress === ""){
                        


                        
                        DB::connection('mysql2')->table('users_activecode')->where('username' , $code)
                            ->update([
                                'exp_date'   => $expiredate,
                                'macadress'  => $mac,
                        ]);

                        $user_exist = DB::connection('mysql2')->table('users')->where('username' , $code)->count();
                       
                        if($user_exist === 0) {
                            $msg = "now";
                             
                            $now = date("Y-m-d H:i:s");
                            $now = strtotime( "$now" );
                            $created_at = $now;
                            $user_activecode = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')->where('username' , $code )->first();
                            DB::connection('mysql2')->table('users')->insert(
                            [
                                'member_id'   =>   $user_activecode->member_id,
                                'created_by'  =>   $user_activecode->created_by,
                                'username'    =>   $user_activecode->username,
                                'password'    =>   $user_activecode->password,
                                'admin_notes' =>    $user_activecode->admin_notes,
                                'reseller_notes' => $user_activecode->reseller_notes,
                                'package_id'  =>   $user_activecode->package_id,
                                'duration_p'  =>   $user_activecode->duration_p, 
                                'duration_in' =>   $user_activecode->duration_in,
                                'bouquet'     =>   $user_activecode->bouquet,
                                'is_trial'    =>   $user_activecode->is_trial,
                                'allowed_ips' =>   '',
                                'allowed_ua'  =>   '',
                                'created_at'  =>   $created_at,
                                'typecode'    =>   2,
                                'exp_date'    => $expiredate,
                                'macadress'  => $mac,

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
                            
                            DB::connection('mysql2')->table('user_output')->insert(
                            [
                                'user_id'   =>     $id,
                                'access_output_id'  =>   3,
                                
                            ]);

                            $dd = ActiveCode::where('number' , $code )->first();
                            if($dd) {}
                            else{
                                $dd = MasterCode::where('number' , $code )->where('mac' , $mac)->first();
                                if($dd) {}
                                else{
                                    $dd = MultiCode::where('number' , $code )->first();
                                }
                            }
                            
                            $dd->update([
                                'mac'  => $mac,
                                'time'    => $string_expiredate,
                            ]);
                            
                        }else{
                            $msg = "already";
                        }
                        
                        // $dd = ActiveCode::where('number' , $code )->first();
                        // if(!$dd) {
                        //     $dd = MasterCode::where('number' , $code )->where('mac' , $mac)->first();   
                        // }else if(!$dd) {
                        //     $dd = MultiCode::where('number' , $code )->first();
                        // }

                        // return response()->json($dd, 401);
   
                            
                        // }
    
                }else if($cc->typecode === "1"  && $cc->exp_date === Null){
                       $msg = "now";

                        DB::connection('mysql2')->table('users_activecode')->where('username' , $cc->username)->where('macadress' , $cc->macadress)
                        ->update([
                            // 'macadress'  => $mac,
                            'exp_date'   => $expiredate,
                            'created_at' => $enabled_at
                        ]);

                        $user_exist = DB::connection('mysql2')->table('users')->where('username' , $code)->count();
                        if($user_exist === 0) {
                            $user_activecode = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')->where('username' , $code )->first();
                            DB::connection('mysql2')->table('users')->insert(
                            [
                                'member_id'   =>   $user_activecode->member_id,
                                'created_by'  =>   $user_activecode->created_by,
                                'username'    =>   $user_activecode->username,
                                'password'    =>   $user_activecode->password,
                                'admin_notes' =>    $user_activecode->admin_notes,
                                'reseller_notes' => $user_activecode->reseller_notes,
                                'package_id'  =>   $user_activecode->package_id,
                                'duration_p'  =>   $user_activecode->duration_p, 
                                'duration_in' =>   $user_activecode->duration_in,
                                'bouquet'     =>   $user_activecode->bouquet,
                                'is_trial'    =>   $user_activecode->is_trial,
                                'allowed_ips' =>   '',
                                'allowed_ua'  =>   '',
                                'created_at'  =>   $enabled_at,
                                'typecode'    =>   2,
                                'exp_date'    => $expiredate,
                                'macadress'  => $user_activecode->macadress,

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
                            
                            DB::connection('mysql2')->table('user_output')->insert(
                            [
                                'user_id'   =>     $id,
                                'access_output_id'  =>   3,
                                
                            ]);
                        }

                        if($master != null){
                            $master->update([
                                // 'mac'  => $mac,
                                'time' => $string_expiredate,
        
                                ]);
                            }


                }else if($cc->typecode === "3"  && $cc->exp_date === Null){

                       $msg = "now";

                        DB::connection('mysql2')->table('users_activecode')->where('username' , $code)
                        ->update([
                            'macadress'  => $mac,
                            'exp_date'   => $expiredate,
                            'created_at' => $enabled_at,
                        ]);


                        $user_exist = DB::connection('mysql2')->table('users')->where('username' , $code)->get();
                        if($user_exist === 0) {
                            $user_activecode = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')->where('username' , $code )->first();
                            DB::connection('mysql2')->table('users')->insert(
                            [
                                'member_id'   =>   $user_activecode->member_id,
                                'created_by'  =>   $user_activecode->created_by,
                                'username'    =>   $user_activecode->username,
                                'password'    =>   $user_activecode->password,
                                'admin_notes' =>    $user_activecode->admin_notes,
                                'reseller_notes' => $user_activecode->reseller_notes,
                                'package_id'  =>   $user_activecode->package_id,
                                'duration_p'  =>   $user_activecode->duration_p, 
                                'duration_in' =>   $user_activecode->duration_in,
                                'bouquet'     =>   $user_activecode->bouquet,
                                'is_trial'    =>   $user_activecode->is_trial,
                                'allowed_ips' =>   '',
                                'allowed_ua'  =>   '',
                                'created_at'  =>   $enabled_at,
                                'typecode'    =>   2,
                                'exp_date'    => $expiredate,
                                'macadress'  => $mac,

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
                            
                            DB::connection('mysql2')->table('user_output')->insert(
                            [
                                'user_id'   =>     $id,
                                'access_output_id'  =>   3,
                                
                            ]);
                        }
                        
                        if($masscode != null){
                            $masscode->update([
                                'mac'  => $mac,
                                'time' => $string_expiredate,
                                ]);
                        }

                }else if($cc->typecode === "3"  && $cc->exp_date !== Null && $cc->macadress === ""){
                        $msg = "now";
                        DB::connection('mysql2')->table('users_activecode')->where('username' , $code)
                            ->update([
                                'exp_date'   => $expiredate,
                                'macadress'  => $mac,
                        ]);

                        $user_exist = DB::connection('mysql2')->table('users')->where('username' , $code)->count();
                        if($user_exist === 0) {
                            $now = date("Y-m-d H:i:s");
                            $now = strtotime( "$now" );
                            $created_at = $now;
                            $user_activecode = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')->where('username' , $code )->first();
                            DB::connection('mysql2')->table('users')->insert(
                            [
                                'member_id'   =>   $user_activecode->member_id,
                                'created_by'  =>   $user_activecode->created_by,
                                'username'    =>   $user_activecode->username,
                                'password'    =>   $user_activecode->password,
                                'admin_notes' =>    $user_activecode->admin_notes,
                                'reseller_notes' => $user_activecode->reseller_notes,
                                'package_id'  =>   $user_activecode->package_id,
                                'duration_p'  =>   $user_activecode->duration_p, 
                                'duration_in' =>   $user_activecode->duration_in,
                                'bouquet'     =>   $user_activecode->bouquet,
                                'is_trial'    =>   $user_activecode->is_trial,
                                'allowed_ips' =>   '',
                                'allowed_ua'  =>   '',
                                'created_at'  =>   $created_at,
                                'typecode'    =>   2,
                                'exp_date'    => $user_activecode->exp_date,
                                'macadress'  => $mac,

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
                            
                            DB::connection('mysql2')->table('user_output')->insert(
                            [
                                'user_id'   =>     $id,
                                'access_output_id'  =>   3,
                                
                            ]);
                        }
                        if($masscode != null){    
                            $masscode->update([
                                'mac'  => $mac,
                                'time' => $string_expiredate,
                            ]);
                        }
                        
                }else {

                    $msg = "already";
                }

                

                // dd($msg);

                if($cc->typecode === "2"){
                    $bb = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')
                    ->where('username' , $code)
                    ->first();
                }
                
                 else if($cc->typecode === "3"){
                    $bb = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')
                    ->where('username' , $code)
                    ->first();
                }
                
                 
                else {
                    $bb = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')
                    ->where('username' , $code)->where('macadress' , $mac)
                    ->first();
                }

                     $datefin = date("Y-m-d H:i:s", $bb->exp_date);
                     
                    if( $bb->username == $code && $bb->macadress == $mac){
                        
                   

                        if($msg == "now"){

                            $message = 'Votre compte est maintenant actif jusqu\'au :' .$datefin;
                            $account = '1';
                            $status = '100';
                            $package_id = $cc->package_id;

                            
                            
                        }else {

                            $message = 'Bienvenue, votre compte est déjà activé';
                            $account = '1';
                            $status = '100';
                            $package_id = $cc->package_id;

                        }
                    }else {
                        $status = '550';
                        $message = 'Erreur. Veuillez appeler le service clientèle.';
                        $account = '0';
                        $package_id = '01';
                        $exp = '0';

                    }
                    // // dd($users);

                   
               

        }else {

            $account = '0';
            $status = '550';
            $message = 'Erreur. Compte invalide';
            $package_id = '01';
            $exp = '0';

            
        }

        return Response()->json([
            'account' => $account,
            'status' => $status,
            'message' => $message,
            'package_id' => $package_id,
            'exp'        => $exp,
        ]);
    

    }
}
