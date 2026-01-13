<?php

namespace App\Http\Controllers;
use App\User;
use App\ActiveCode;
use App\MultiCode;
use App\MasterCode;

use App\Channel;
use App\Category;
use App\Message;
use DB;
use Carbon\Carbon;
use App\Notification;



use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProtocoleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    

    public function activate(){


        $code=  $_GET["a"]; 
        $m=  $_GET["m"]; 
        $mac = strtolower($m);
        $kk="0";
        $tt="";
        
        $dd = ActiveCode::where('number' , $code )->first();
        if($dd) {}
        else{
            $dd = MasterCode::where('number' , $code )->where('mac' , $mac)->first();   
            if($dd) {}
            else{
                $dd = MultiCode::where('number' , $code )->first();
            }
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
                if($current) {
                    ActiveCode::whereId($dd->id)->update(['mac' => $mac]);
                }
                else {
                    $current = DB::table('master_codes')->where('id', $dd->id)->first();
                    
                    if($current) {
                        MasterCode::whereId($dd->id)->update(['mac' => $mac]);
                    }else{
                        $current = DB::table('multi_codes')->where('id', $dd->id)->first();
                        if($current) {
                            MasterCode::whereId($dd->id)->update(['mac' => $mac]);
                        }
                    }
                }   


                DB::connection('mysql2')->table('users')->where('users.username' , $current->number)->update(['users.macadress' => $mac]);
            }
        }

        // dd($masscode);
        $mm = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')
        ->where('username' , $code)
        ->get();

        // dd($mm);
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
                ->where('username' , $code)->where('macadress' , $mac)
                ->first();
            }

        }
    }else{

        $mm = DB::connection('mysql2')->table('users')->select('users.*')
        ->where('username' , $code)
        ->get();

        if($mm){
            $cc = DB::connection('mysql2')->table('users')->select('users.*')
                ->where('username' , $code)
                ->first();
        }else{
            $account = '0';
            $status = '550';
            $message = 'Error. Invalid Account';
            $package_id = '01';
            $exp = '0';
        }

    }

        // dd($cc);
        
        // dd($cc);
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
                    $string_expiredate = date("Y-m-d", $expiredate);
            }else if($dur == '10'){
                    $expiredate= strtotime( "+10 days" );
                    $string_expiredate = date("Y-m-d", $expiredate);
            }else if($dur == '30'){
                    $expiredate= strtotime( "+1 month" );
                    $string_expiredate = date("Y-m-d", $expiredate);

            }else if($dur == '60'){
                    $expiredate= strtotime( "+2 month" );
                    $string_expiredate = date("Y-m-d", $expiredate);
            }else if($dur == '90'){
                    $expiredate= strtotime( "+3 month" );
                    $string_expiredate = date("Y-m-d", $expiredate);

            }else if($dur == '180'){
                    $expiredate= strtotime( "6 month" );
                    $string_expiredate = date("Y-m-d", $expiredate);

            }else if($dur == '365' || $dur == '366' ){
                    $expiredate= strtotime( "+12 month" );
                    $string_expiredate = date("Y-m-d", $expiredate);
            }else{
                $expiredate= strtotime( "+".$dur." month" );
                $string_expiredate = date("Y-m-d", $expiredate);
            }
            
            $if_expired = DB::connection('mysql2')->table('users')->where('username' , $code)->first();
            if($if_expired) {
                if ($if_expired->macadress != "" && time() > $if_expired->exp_date) {
                    $status = '550';
                    $message = 'Error : Expired account';
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
            

            // dd($exp);
            
            if($cc->typecode == "2" && ($cc->exp_date == Null || $cc->exp_date == 0)){
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

            }else if($cc->typecode == "2" && $cc->exp_date != Null && $cc->macadress == ""){

                        
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
                        
                        $dd = ActiveCode::where('number' , $code )->first();

                        // if($dd != null){    
                            $dd->update([
                                'mac'  => $mac,
                                'time'    => $string_expiredate,
                            ]);
                        // }
                        // return response()->json($dd);
    
    
            }else if($cc->typecode == "2" && $cc->exp_date != Null && $cc->macadress == "Mac Reseted"){
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
                        
                        $dd = ActiveCode::where('number' , $code )->first();

                        // if($dd != null){    
                            $dd->update([
                                'mac'  => $mac,
                                'time'    => $string_expiredate,
                            ]);
                        // }
                        // return response()->json($dd);
    
    
            }else if($cc->typecode == "1"  && $cc->exp_date == Null){

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


            }else if($cc->typecode == "3"  && $cc->exp_date == Null){

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

            }else if($cc->typecode == "3"  && $cc->exp_date != Null && $cc->macadress == ""){
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
                    $bb = DB::connection('mysql2')->table('users')->select('users.*')
                    ->where('username' , $code)->where('macadress' , $mac)
                    ->first();
                }


                if($cc->typecode === "2"){
                    $gg = DB::connection('mysql2')->table('users')->select('users.*')
                    ->where('username' , $code)
                    ->first();
                }
                
                 else if($cc->typecode === "3"){
                    $gg = DB::connection('mysql2')->table('users')->select('users.*')
                    ->where('username' , $code)
                    ->first();
                }
                
                 
                else {
                    $gg = DB::connection('mysql2')->table('users_activecode')->select('users_activecode.*')
                    ->where('username' , $code)->where('macadress' , $mac)
                    ->first();
                }

                    if($bb) {}
                    else{
                        $bb = $gg;
                    }
                    $datefin = date("Y-m-d", $bb->exp_date);
                    if($bb) {
                        if( $bb->username == $code && $bb->macadress == $mac){
                        
                   

                            if($msg == "now"){

                                $message = 'Your Account is now Active until : ' .$datefin;
                                $account = '1';
                                $status = '100';
                                $package_id = $cc->package_id;
                                $exp = date("Y-m-d H:i:s", $bb->exp_date);

                                
                                
                            }else {

                                $gg = DB::connection('mysql2')->table('users')->select('users.*')
                                ->where('username' , $code)->where('macadress' , $mac)
                                ->first();

                                $message = 'Welcome, your account is already activated';
                                $account = '1';
                                $status = '100';
                                $exp = date("Y-m-d H:i:s", $gg->exp_date);
                                $package_id = $cc->package_id;

                            }
                        }else {
                            // $status = '550';
                            // $message = 'Error. Please call customer care ! oki';
                            // $account = '0';
                            // $package_id = '01';
                            // $exp = '0';

                            if( $gg->username == $code && $gg->macadress == $mac){
                        
                   

                                if($msg == "now"){
    
                                    $message = 'Your Account is now Active until : ' .$datefin;
                                    $account = '1';
                                    $status = '100';
                                    $package_id = $cc->package_id;
                                    $exp = date("Y-m-d H:i:s", $gg->exp_date);
                                    
                                    
                                }else {

                                    $gg = DB::connection('mysql2')->table('users')->select('users.*')
                                    ->where('username' , $code)->where('macadress' , $mac)
                                    ->first();
    
                                    $message = 'Welcome, your account is already activated';
                                    $account = '1';
                                    $status = '100';
                                    $exp = date("Y-m-d H:i:s", $gg->exp_date);
                                    $package_id = $cc->package_id;
    
                                }
                            }else {
                                $status = '550';
                                $message = 'Error. Please call customer care ! oki';
                                $account = '0';
                                $package_id = '01';
                                $exp = '0';
    
                            }

                        }
                    }
                     
                    
                    // // dd($users);

                   
               

         }else {

            $account = '0';
            $status = '550';
            $message = 'Error. Invalid Account';
            $package_id = '01';
            $exp = '0';

            
         }

        if($status == '100') {

            $user = DB::connection('mysql2')->table('users')->select('users.*')->where('username' , $code )->where('macadress' , $mac)->first();
            $streams_category_id = DB::connection('mysql2')->table('streams')->where('streams.category_id', '!=', null)->where('streams.category_id', '!=', 0)->distinct()->get('streams.category_id');
            $allcat =  DB::connection('mysql2')->table('streams')->where('streams.category_id', '!=', null)->where('streams.category_id', '!=', 0)->distinct()->pluck('category_id');

            $url = 'http://kingtop10.net:7070/panel_api.php?username='.$user->username.'&password='.$user->password;
            $category_live = DB::connection('mysql2')->table('stream_categories')->whereIn('stream_categories.id',$allcat)->where('stream_categories.category_type' , '=' , 'live')
                ->orderBy('stream_categories.cat_order')->get();
            $json = json_decode(file_get_contents($url), true);
            $programme = [];
            
            $res_cat_live = [];

            if(!isset($_GET['type'])){
                $obj=[];
                // foreach ($category_live as $catLive) {  
                //     $rr['id'] = $catLive->id;
                //     $rr['caption'] = $catLive->category_name;
                //     $rr['icon_url'] = $catLive->category_image;                    
                    
                //     $res_live = [];
                //     foreach ($json['available_channels'] as $channel) {
                //         if($channel['category_id'] == $catLive->id && $channel['stream_type'] == "live"){
                //             $as['icon_url'] = $channel['stream_icon'];
                //             $as['caption'] = $channel['name'];
                //             $as['streaming_url'] = "http://kingtop10.net:7070/".$user->username."/".$user->password."/".$channel['stream_id'];
                //             $as['streaming_url_m3u8'] = "http://kingtop10.net:7070/live/".$user->username."/".$user->password."/".$channel['stream_id'].".m3u8";                        
                //             $res_live[] = $as;
                //         }
                //     }                            
                //     $rr['tv_channel']=$res_live;
                //     $res_cat_live[] = $rr;                            
                // }                         
                // $obj=[];
                // $obj['tv_categories']=[];
                // $obj['tv_categories']['tv_category']=$res_cat_live;
            }else{
                foreach ($category_live as $catLive) {  
                    $rr['id'] = $catLive->id;
                    $rr['caption'] = $catLive->category_name;
                    $rr['icon_url'] = $catLive->category_image;                    
                    $res_live = [];                       
                    $res_cat_live[] = $rr;                            
                }   
                foreach ($json['available_channels'] as $channel) {
                    if($channel['stream_type'] == "live"){
                        $as['icon_url'] = $channel['stream_icon'];
                        $as['caption'] = $channel['name'];
                        $as['category_id'] = $channel['category_id'];
                        $as['streaming_url'] = "http://kingtop10.net:7070/".$user->username."/".$user->password."/".$channel['stream_id'];
                        // $as['streaming_url_m3u8'] = "http://kingtop10.net:7070/live/".$user->username."/".$user->password."/".$channel['stream_id'].".m3u8";
                        
                        $res_live[] = $as;
                    }
                }                         
                $rr['tv_channel']=$res_live;
                $obj=[];
                $obj['tv_categories']=[];

                $obj['tv_categories']['tv_category']=$res_cat_live;
                $obj['tv_channel']=$res_live;
            }

            return Response()->json([
                'account' => $account,
                'status' => $status,
                'message' => $message,
                'package_id' => $package_id,
                'exp'        => $exp,
                'data' => $obj,
            ]);
        }else{
            return Response()->json([
                'account' => $account,
                'status' => $status,
                'message' => $message,
                'package_id' => $package_id,
                'exp'        => $exp
            ]);
        }

    }
    
    
 // ######################################################    function Run    ########################################################################################################


    public function Run(){
        
        if(isset($_GET['a'])) {  
           
    
                // GET Variable with request ###############################################################################
                
                $code  =  $_GET["a"]; 
                $mac   =  $_GET["m"]; 
                $type  =  $_GET["p"]; 
                
            // return Response()->json([
            //             'status' => '550',
            //             'message' => $mac,
            //             'account' => '002',
            //             'package_id' => '01',
            //         ]);
                
                
                // Find of User with Code  ###############################################################################
                
                $user = DB::connection('mysql2')->table('users')->select('users.*')->where('username' , $code )
                ->where('macadress' , $mac)->first();

                // if(!$user) {
                //     $mac = str_replace(":" , "", $mac);
                //     $user = DB::connection('mysql2')->table('users')->select('users.*')->where('username' , $code )
                //     ->where('macadress' , $mac)->first();
                // }
                
                // dd($user);
if($user){
    
                        
                
                //GET Bouquets of User ###############################################################################
                // $user_bq = $user->bouquet; 
                
                // dd($user_bq);
              
                //  $bouquetIDs = json_decode('[' . $user_bq . ']', true);
                 
                 
                 // GET all  IDS of category_id in Table Streams ###############################################################################
        
                
                 $streams_category_id = DB::connection('mysql2')->table('streams')->where('streams.category_id', '!=', null)->where('streams.category_id', '!=', 0)->distinct()->get('streams.category_id');
                 
                 // Stock all  IDS of category_id in Table  ###############################################################################
                 
                 $allcat = [];
                  foreach ($streams_category_id as $cat_id) { 
                    //   $tt['id'] = $cat_id->category_id;
                       $allcat[] = $cat_id->category_id;
                      
                  }
                  
// channelsList ###############################################################################

                 if($type == 'channelsList'){
                     
                        // GET bouquets type live ###############################################################################
                        
                    //   $bouquets_live = DB::connection('mysql2')->table('bouquets')->whereIn('bouquets.id',$bouquetIDs[0])->where('bouquets.type' , '=' , 'live')
                    //   ->OrWhere('bouquets.type' , '=' , '')->get();
                       
                    //   $b = DB::connection('mysql2')->table('bouquets')->where('bouquets.id' , '=' , 11)->get();
                    //   dd($b);
                       
    
                       
                        // Stock all IDS channel de Bouquet Type Live  ###############################################################################
                        
                    //   $ch_live = [];
                    //       foreach($bouquets_live as $bq_live){
                    //           $ch_live[] = $bq_live->bouquet_channels;
                              
                    //       }
                    
                    //     $ll = str_replace('"', '', $ch_live);  
                        
                    //     $live = json_decode('[' . $ll[0] . ']', true);
                        
                    //     // dd($live);
        
                        // Stock all  channel in Variables  ###############################################################################
                
                        // $streams_live = DB::connection('mysql2')->table('streams')->whereIn('streams.id', $live[0])->where('streams.type' ,'=', 1)
                        // ->orderBy('streams.id' , 'DESC')->get();
                        
                        // ->where('streams.category_id' , 30)
                        
                        // dd($streams_live);
                        
                        
                        
                
                if(isset($_GET['id']) && $_GET['id'] != ''){
                    
                    // Stock all  Category de type live in new array   ###############################################################################
                        
                        $url = 'http://kingtop10.net:7070/panel_api.php?username='.$user->username.'&password='.$user->password;

                        
                        $json = json_decode(file_get_contents($url), true);
                        
                              $res_live = [];
                  foreach ($json['available_channels'] as $channel) {
                    //   dd($channel); 
                         if($channel['category_id'] == $_GET['id'] && $channel['stream_type'] == "live"){
                    	    $as['id']   = $channel['num'];
                    	    $as['stream_id']   = $channel['stream_id'];
                            $as['icon_url'] = $channel['stream_icon'];
                            $as['caption'] = $channel['name'];
                            $as['category_name'] = $channel['category_name'];
                            $as['type'] = $channel['type_name'];
                            $as['streaming_url'] = "http://kingtop10.net:7070/".$user->username."/".$user->password."/".$channel['stream_id'];
                            $as['streaming_url_m3u8'] = "http://kingtop10.net:7070/live/".$user->username."/".$user->password."/".$channel['stream_id'].".m3u8";
                            // $as['tv_categories'] = [];
                            // $as['tv_categories'][]['tv_category_id'] = $channel['category_id'];
                            $as['category_id'] = $channel['category_id'];
                         
                          
                        $res_live[] = $as;
                         }
                         
                  }
                  
                  $obj=[];
                        // $obj['tv_categories']=[];
                
                        // $obj['tv_categories']['tv_category']=$res_cat_live;
                        $obj['tv_channel']=$res_live;
                
                
                        // return json_encode($obj);
                        // dd($obj);
                        
                        return Response()->json($obj);
                  
                // //   dd($res_live);
                  
                  
                  
                //         $obj=[];
                //         // $obj['tv_categories']=[];
                
                //         // $obj['tv_categories']['tv_category']=$res_cat_live;
                //         $obj['tv_channel']=$res_live;
                
                
                //         // return json_encode($obj);
                //         // dd($obj);
                        
                //         return Response()->json($obj);
                        

                    
                }else{

                    

                        $url = 'http://kingtop10.net:7070/panel_api.php?username='.$user->username.'&password='.$user->password;
                        $category_live = DB::connection('mysql2')->table('stream_categories')->whereIn('stream_categories.id',$allcat)->where('stream_categories.category_type' , '=' , 'live')
                         ->orderBy('stream_categories.cat_order')->get();
                        $json = json_decode(file_get_contents($url), true);
                        $programme = [];
                        
                        $res_cat_live = [];

                        if(!isset($_GET['type'])){
                    
                            foreach ($category_live as $catLive) {  
                                $rr['id'] = $catLive->id;
                                $rr['caption'] = $catLive->category_name;
                                $rr['icon_url'] = $catLive->category_image;                    
                                
                                $res_live = [];
                                foreach ($json['available_channels'] as $channel) {
                                    if($channel['category_id'] == $catLive->id && $channel['stream_type'] == "live"){
                                        $as['icon_url'] = $channel['stream_icon'];
                                        $as['caption'] = $channel['name'];
                                        $as['streaming_url'] = "http://kingtop10.net:7070/".$user->username."/".$user->password."/".$channel['stream_id'];
                                        $as['streaming_url_m3u8'] = "http://kingtop10.net:7070/live/".$user->username."/".$user->password."/".$channel['stream_id'].".m3u8";
                                        $as['epg_channel_id'] = $channel['epg_channel_id'];                              
                                        
                                        $res_live[] = $as;
                                    }
                                }                            
                                $rr['tv_channel']=$res_live;
                                $res_cat_live[] = $rr;                            
                            }                         
                            $obj=[];
                            $obj['tv_categories']=[];
                            $obj['tv_categories']['tv_category']=$res_cat_live;
                        }else{
                            foreach ($category_live as $catLive) {  
                                $rr['id'] = $catLive->id;
                                $rr['caption'] = $catLive->category_name;
                                $rr['icon_url'] = $catLive->category_image;                    
                                $res_live = [];                       
                                $res_cat_live[] = $rr;                            
                            }   
                            foreach ($json['available_channels'] as $channel) {
                                if($channel['stream_type'] == "live"){
                                    $as['icon_url'] = $channel['stream_icon'];
                                    $as['caption'] = $channel['name'];
                                    $as['category_id'] = $channel['category_id'];
                                    $as['streaming_url'] = "http://kingtop10.net:7070/".$user->username."/".$user->password."/".$channel['stream_id'];
                                    // $as['streaming_url_m3u8'] = "http://kingtop10.net:7070/live/".$user->username."/".$user->password."/".$channel['stream_id'].".m3u8";
                                    
                                    $res_live[] = $as;
                                }
                            }                         
                            $rr['tv_channel']=$res_live;
                            $obj=[];
                            $obj['tv_categories']=[];
                    
                            $obj['tv_categories']['tv_category']=$res_cat_live;
                            $obj['tv_channel']=$res_live;
                        }
                        return Response()->json($obj);
                }
                         
                //           $json = json_decode(file_get_contents('http://kingtop10.net:7070/panel_api.php?username='.$user->username.'&password='.$user->password), true);
                        
                //               $res_live = [];
                //   foreach ($json['available_channels'] as $channel) {
                //     //   dd($channel); 
                //         //  if($channel['category_id'] == $_GET['id']){
                //         // if($channel['stream_type'] == "live"){
                //     	    $as['id']   = $channel['num'];
                //     	    $as['stream_id']   = $channel['stream_id'];
                //             $as['icon_url'] = $channel['stream_icon'];
                //             $as['caption'] = $channel['name'];
                //             $as['category_name'] = $channel['category_name'];
                //             $as['type'] = $channel['type_name'];
                //             $as['streaming_url'] = "http://kingtop10.net:7070/".$user->username."/".$user->password."/".$channel['stream_id'];
                //             $as['tv_categories'] = [];
                //             $as['tv_categories'][]['tv_category_id'] = $channel['category_id'];
                //             // $as['category_id'] = $channel['category_id'];
                         
                          
                //         $res_live[] = $as;
                //         //  }
                         
                //   }
                  
                //   dd($res_live);
                  
                  
                  
                         
                      
                // }

                
                  
                
                
                        
                        // return $json;
                        
                  
               


                        
                         
                    
                        // dd($res_cat_live);
                
                        // Stock all  channel in new array   ###############################################################################
                        
                    //     $res_live = [];
                    //  foreach ($streams_live as $st_live) {  
                    //     $bb['id'] = $st_live->id;
                    //     $bb['caption'] = $st_live->stream_display_name;
                    //     $bb['icon_url'] = $st_live->stream_icon;
                    //     $bb['num_past_epg_days'] = '2';
                    //     $bb['streaming_url'] = "http://kingtop10.net:7070/".$user->username."/".$user->password."/".$st_live->id;
                    //     $bb['tv_categories'] = [];
                    //     $bb['tv_categories'][]['tv_category_id'] =  $st_live->category_id;
                    //     $res_live[] = $bb;
                    // }
                    
                
                        
                        
// channels Vod ###############################################################################
                        
            
}else if($type == 'Movie'){
                     
                    // GET bouquets type Movie  ###############################################################################
                    // dd($bouquetIDs);

                //     $bouquets_vod = DB::connection('mysql2')->table('bouquets')->whereIn('bouquets.id',$bouquetIDs[0])->where('bouquets.type' , '=' , 'movie')->get();
                    
                //     // dd($bouquets_vod);
                //     // Stock all IDS channel de Bouquet Type Movie  ###############################################################################
                    
                // if($bouquets_vod->isNotEmpty()) { 
                    
                //     $ch_vod = [];
                //         foreach($bouquets_vod as $bq_vod){
                //             $ch_vod[] = $bq_vod->bouquet_channels;
                //         }
                        
                        
                //     $kk = str_replace('"', '', $ch_vod);  
                    
                //     $vod = json_decode('[' . $kk[0] . ']', true);
                    
                //     // dd($vod);
                    
                //     $streams_vod = DB::connection('mysql2')->table('streams')->whereIn('streams.id', $vod[0])->where('streams.type' ,'=', 2)->get();
                
            if(isset($_GET['id']) && $_GET['id'] != ''){
                
                

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_URL, 'http://kingtop10.net:7070/panel_api.php?username='.$user->username.'&password='.$user->password);
                $result = curl_exec($ch);
                curl_close($ch);
                $json = json_decode(($result), true);
                
                // dd($result);

                // $json = json_decode(file_get_contents($url), true);
                        
                              $res_live = [];
                              $res_Vod = [];
                if($json['available_channels']) {
                  foreach ($json['available_channels'] as $channel) {
                    //   dd($channel); 
                         if($channel['category_id'] == $_GET['id'] && $channel['stream_type'] == "movie"){
                        // if($channel['stream_type'] == "movie"){
                    	    $bb['id']   = $channel['num'];
                    	    $bb['stream_id']   = $channel['stream_id'];
                            $bb['poster_url'] = $channel['stream_icon'];
                            $bb['caption'] = $channel['name'];
                            $bb['category_name'] = $channel['category_name'];
                            $bb['type'] = $channel['type_name'];
                            $bb['ext'] = $channel['container_extension'];
                            $bb['streaming_url'] = "http://kingtop10.net:7070/movie/".$user->username."/".$user->password."/".$channel['stream_id'].".".$channel['container_extension'];
                            // $bb['tv_categories'] = [];
                            // $bb['tv_categories'][]['tv_category_id'] = $channel['category_id'];
                            $bb['category_id'] = $channel['category_id'];
                         
                            if($channel['container_extension'] != "avi"){
                                 $res_Vod[] = $bb;
                            }
                         }
                  }
                }
                         $obj=[];
                        // $obj['vod_categories']=[];
    
                        // $obj['vod_categories']['vod_category']=$res_cat_Movie;
                        $obj['movies']=$res_Vod;
    
    
                        // return json_encode($obj);
                        
                        return Response()->json($obj);
                         
                  }else{
                      
                      $category_movie = DB::connection('mysql2')->table('stream_categories')->whereIn('stream_categories.id',$allcat)
                    ->where('stream_categories.category_type' , '=' , 'movie')->orderBy('stream_categories.cat_order')->get();

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_URL, 'http://kingtop10.net:7070/panel_api.php?username='.$user->username.'&password='.$user->password);
                    $result = curl_exec($ch);
                    curl_close($ch);
                    $json = json_decode(($result), true);
                
                    // dd($category_movie);


                    // Stock all  Category de type Movie in new array   ###############################################################################
                    
                        $res_cat_Movie = [];
                
                    foreach ($category_movie as $catMovie) {  
                        $rr['id'] = $catMovie->id;
                        $rr['caption'] = $catMovie->category_name;
                        $rr['icon_url'] = $catMovie->category_image;
                
                        $res_cat_Movie[] = $rr;
                    }
                
                    // dd($res_cat_Movie);

                    // Stock all  channel in new array   ###############################################################################
                    
                
                
                // dd($user->password);
                

                    $obj=[];
                    $obj['vod_categories']=[];

                    $obj['vod_categories']['vod_category']=$res_cat_Movie;

                    $res_live = [];
                              $res_Vod = [];
                    if($json['available_channels']) {
                      foreach ($json['available_channels'] as $channel) {
                        //   dd($channel); 
                             if($channel['stream_type'] == "movie"){
                            // if($channel['stream_type'] == "movie"){
                                $bb['id']   = $channel['num'];
                                // $bb['stream_id']   = $channel['stream_id'];
                                $bb['poster_url'] = $channel['stream_icon'];
                                $bb['caption'] = $channel['name'];
                                // $bb['category_name'] = $channel['category_name'];
                                // $bb['type'] = $channel['type_name'];
                                // $bb['ext'] = $channel['container_extension'];
                                $bb['streaming_url'] = "http://kingtop10.net:7070/movie/".$user->username."/".$user->password."/".$channel['stream_id'].".".$channel['container_extension'];
                                // $bb['tv_categories'] = [];
                                // $bb['tv_categories'][]['tv_category_id'] = $channel['category_id'];
                                $bb['category_id'] = $channel['category_id'];
                             
                                if($channel['container_extension'] != "avi"){
                                     $res_Vod[] = $bb;
                                }
                             }
                      }
                    }
                        // $obj['vod_categories']=[];
    
                        // $obj['vod_categories']['vod_category']=$res_cat_Movie;
                        $obj['movies']=$res_Vod;

                    // $obj['movies']=$res_Vod;


                    // return json_encode($obj);
                    
                    return Response()->json($obj);
                  }
                    
                    //     $res_Vod = [];
                    // foreach ($streams_vod as $st_Vod) {  
                    //     $bb['id'] = $st_Vod->id;
                    //     $bb['caption'] = $st_Vod->stream_display_name;
                    //     $bb['poster_url'] = $st_Vod->stream_icon;
                    //     $bb['num_past_epg_days'] = '2';
                    //     $bb['v_url'] = "http://kingtop10.net:7070/movie/".$user->username."/".$user->password."/".$st_Vod->id.".".$st_Vod->target_container;
                    //     $bb['vod_category'] = [];
                    //     $bb['vod_category'][]['vod_category_id'] =  $st_Vod->category_id;
                    //     $bb['genere'] = 'N/A';
                    //     if($st_Vod->target_container != "avi" && $st_Vod->target_container != "[\"avi\"]"){
                    //         $res_Vod[] = $res_Vod;
                    //     }
                    // }
                
                    
                    // dd($streams_vod[0]);
                    
                    // GET  all category de Type Live   ###############################################################################
                        
                    
                    
                // }else{
                    
                //     $err = 'Movies Not Found';
                    
                //     return Response()->json(['err' =>$err]);
                // }
                
            }
// channels series ###############################################################################

                    else if($type == 'Series'){


                        if(isset($_GET['id']) && $_GET['id'] != ''){

                            $episodes = DB::connection('mysql2')->table('series_episodes')->where('series_episodes.series_id' , $_GET['id'])
                            ->join('streams', 'series_episodes.stream_id', '=', 'streams.id')->select('series_episodes.*' , 'streams.id','streams.target_container')
                            ->get();
                            
                            // dd($episodes);

                            $oneserie = DB::connection('mysql2')->table('series')->where('series.id' , $_GET['id'])->first();

                            foreach ($episodes as $ep) {  
                               
                                $ee['season_num'] = $ep->season_num;
                                $ee['series_id']  = $ep->series_id;
                                $ee['stream_id']  = $ep->stream_id;
                                $ee['sort']       = $ep->sort;
                                $targetContainer =  trim($ep->target_container, "[");
                                $targetContainer =  trim($targetContainer, "]");
                                $targetContainer =  trim($targetContainer, "\"");
                                $ee['url']        = "http://kingtop10.net:7070/series/".$user->username."/".$user->password."/".$ep->stream_id.'.'.$targetContainer;
                                $eps[] = $ee; 
                                $array[] = $ep->season_num;
                                $nbrSeason = array_unique($array);
                                sort($nbrSeason);
                                $sizearray = array_values($nbrSeason);
                            }

                            return response()->json([
                                'title'        => $oneserie->title,
                                'desc'         => $oneserie->plot,
                                'genre'        => $oneserie->genre,
                                'rating'       => $oneserie->rating,
                                'releaseDate'  => $oneserie->releaseDate,
                                'director'     => $oneserie->director, 
                                'cast'         => $oneserie->cast,
                                'time'         => $oneserie->episode_run_time,
                                'season'       => $sizearray,
                                'series'       => $eps,
                            ]);


                            // $eps = [];
                            // foreach ($episodes as $ep) {  
                               
                            //     $ee['title']       = $oneserie->title;
                            //     $ee['desc']        = $oneserie->plot;
                            //     $ee['genre']       = $oneserie->genre;
                            //     $ee['rating']      = $oneserie->rating;
                            //     $ee['releaseDate'] = $oneserie->releaseDate;
                            //     $ee['director']    = $oneserie->director;
                            //     $ee['cast ']       = $oneserie->cast;
                            //     // $ee['season_num '] = $ep->season_num;
                            //     // $ee['sort']        = $ep->sort;
                        
                            //     $eps[] = $ee;
                            // }
                            // return response()->json($eps);

                        // return response()->json([
                        //     'title'  => $streams_series['title'],
                        //     'cover'  => $streams_series['cover_big'],
                        //     'desc'   => $streams_series['plot'],
                        //     ]);

                        }else{

                            $package = DB::connection('mysql2')->table('packages')->find($user->package_id);
                            $decode = json_decode($package->bouquets);

                            // $package = DB::connection('mysql2')->table('bouquets')->get();

                            $streams_series = DB::connection('mysql2')->table('series')->get();
                            // dd($streams_series);

                            $azz = [];

                            foreach ($streams_series as $sr) {
                                $qs['id'] = $sr->id;
                                $qs['title'] = $sr->title;
                                $qs['cover_big'] = $sr->cover;
                                $qs['category_id'] = $sr->category_id;
                                // $qs['plot'] = $sr->plot;
                        
                                $azz[] = $qs;
                            }    

                            return response()->json($azz);

                        }

                         
              
                    
                    
                    
                    } else if ($type == 'Radio'){
                        $streams_category_id = DB::connection('mysql2')->table('streams')->where('streams.category_id', '!=', null)->where('streams.category_id', '!=', 0)->distinct()->get('streams.category_id');
                     
                     // Stock all  IDS of category_id in Table  ###############################################################################
                     
                     $allcat = [];
                      foreach ($streams_category_id as $cat_id) { 
                        //   $tt['id'] = $cat_id->category_id;
                           $allcat[] = $cat_id->category_id;
                          
                      }
    
                        $streams_series = DB::connection('mysql2')->table('streams')->where('streams.type' ,'=', 4)->get();
                            
                        // dd($streams_vod[0]);
                        
                        // GET  all category de Type Live   ###############################################################################
                            
                        $category_series = DB::connection('mysql2')->table('stream_categories')->whereIn('stream_categories.id',$allcat)->where('stream_categories.category_type' , '=' , 'live')->get();
                    
                        // dd($category_movie);
    
    
                        // Stock all  Category de type Movie in new array   ###############################################################################
                        
                            $res_cat_radio = [];
                    
                        foreach ($category_series as $catseries) {  
                            $ra['id'] = $catseries->id;
                            $ra['caption'] = $catseries->category_name;
                            // $ra['icon_url'] = $catseries->category_image;
                    
                            $res_cat_radio[] = $ra;
                        }
                    
                        // dd($res_cat_Movie);
    
                        // Stock all  channel in new array   ###############################################################################
                        
                        $res_series = [];
                        foreach ($streams_series as $st_series) {  
                        $ba['id'] = $st_series->id;
                        $ba['caption'] = $st_series->stream_display_name;
                        $ba['poster_url'] = $st_series->stream_icon;
                        $ba['num_past_epg_days'] = '2';
                        $ba['ext']               = $st_series->target_container;
                        $ba['v_url'] = "http://kingtop10.net:7070/".$user->username."/".$user->password."/".$st_series->id;
                        $ba['v_url_m3u8'] = "http://kingtop10.net:7070/live/".$user->username."/".$user->password."/".$st_series->id.".m3u8";
                        $ba['radio_category'] = [];
                        $ba['radio_category'][]['radio_category_id'] =  $st_series->category_id;
                        $ba['genere'] = 'N/A';
                        $res_series[] = $ba;
                    }
                    
                    // dd($res_series);
                    
    
                        $obj=[];
                        $obj['radio_categories']=[];
    
                        $obj['radio_categories']['radio_category']=$res_cat_radio;
                        $obj['radio']=$res_series;
    
    
                        // return json_encode($obj);
                        
                        return Response()->json($obj);                
                    }     
                } else {
                    return Response()->json([
                        'status' => '550',
                        'message' => 'Error. Please call customer care ! oki',
                        'account' => '0',
                        'package_id' => '01',
                    ]);
                }
        
        }else {
                return Response()->json([
                    'Msg ID' => '2',
                    'Status' => '201',
                    'Msg' => 'Your software is at latest version',
                    'Link' => 'http://www.ndasat.com/soft/enigma2-plugin-extensions-ndatv_2.1_all.ipk',
                ]);
        
         }
        
            }
            
        public function channel(){

                // $code = '19091244';
                $code = $_GET['a'];
        
                $user = DB::connection('mysql2')->table('users')->select('users.*')->where('username' , $code)->first();
        
                $playlist = [];
                $arraySerie = [];
                $i = 0;
                $id = 1;
                // $pls_header = "#EXTM3U";


                if($user){
                    $json = json_decode(file_get_contents('http://kingtop10.net:7070/panel_api.php?username='.$code.'&password='.$user->password), true);
                    // return $json;
                    
                   foreach ($json['available_channels'] as $channel) {
                    //   dd($channel);
                    // 	$playlist[$i]['name'] = "id="{$id}"
                    // 	logo="{$channel['stream_icon']}"
                    // 	channel-name="{$channel['name']}"
                    // 	category_name="{$channel['category_name']}"
                    // 	type="{$channel['stream_type']}";
                    	    $as['id']   = $channel['num'];
                            $as['logo'] = $channel['stream_icon'];
                            $as['name'] = $channel['name'];
                            $as['category_name'] = $channel['category_name'];
                            $as['type'] = $channel['type_name'];
                            $as['category_id'] = $channel['category_id'];
                          
                        $playlist[] = $as;
                         
                   }
                        //  dd($playlist);
                         
                   foreach ($playlist as $tt) {
                    //   dd($tt);
                   
                    	if($tt['type'] === 'Movies'){
                    	    
                        $arraySerie[] = $tt;
                        
                     }
                        $i++;

                    }
                    
                    // // $playlist_final = $pls_header."\n";
                    // foreach($playlist as $line){
                    // 	"{$line['name']}\n";
                    // }


                    return $arraySerie;
                }else {
                    return  'invalid account';
                }
                

             }

             public function notifications(){
                $Notification = Notification::all();
                return Response()->json($Notification);

             }

}