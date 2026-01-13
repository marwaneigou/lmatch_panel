<?php

namespace App\Http\Controllers;

use App\User;
use App\SubResiler;
use App\ActiveCode;
use App\MagDevice;
use App\MultiCode;
use App\MasterCode;
use App\ResellerStatistic;
use App\Package;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;


use DB;
use File;


class ResilerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */


    public function index()
    {
        // $ActiveCode = ActiveCode::all();
        // return Response()->json($ActiveCode);

        // return User::latest()->paginate(10);

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        $query = request('query');

        // return ActiveCode::find($user_id)->latest()->paginate(10);
        if($user_type !== 'Admin'){

            $model = new SubResiler;
            $SubResiler =  $model;

            // $SubResiler =  DB::table('sub_resilers')
            $SubResiler = $SubResiler->join('users', 'sub_resilers.user_id', '=', 'users.id');
            $SubResiler = $SubResiler->select('users.*');
            $SubResiler = $SubResiler->where('sub_resilers.res_id', '=', $user_id);
            $SubResiler = $SubResiler->where('users.deleted_at' , null);
            if($query) $SubResiler = $SubResiler->where('users.name','LIKE', "%{$query}%");
            $SubResiler = $SubResiler->orderBy('id', 'ASC')->paginate(20);

            // dd($users);
            return Response()->json($SubResiler);

        }else {



            $dd = new User;
            $users =  $dd;

            // $users =  DB::table('users')
            // ->where('users.type', '=', 'SubResiler')
            $users = $users->select('users.*');
            $users = $users->where('deleted_at' , null);
            if($query) $users = $users->where('users.name','LIKE', "%{$query}%");

            $users = $users->orderBy('id', 'ASC')->paginate(20);
            // $kk= [];
            // foreach($users as $user){
            //     $uu['id'] = $user->id;
            //     $uu['email'] = $user->email;
            //     $uu['name'] = $user->name;
            //     $uu['password'] = $user->password;
            //     $uu['phone'] = $user->phone;
            //     $uu['solde'] = $user->solde;
            //     $uu['type'] = $user->type;
            //     $ll = str_replace('"', '', $user->package_id);
            //     $uu['package_id'] = $ll;

            //     $kk[] = $uu;
            // }

            // $rr = $kk->orderBy('id', 'ASC')->paginate(20);

            // dd($kk);

            foreach ($users as $is_sub) {
                $is_sub->owner = '';
                $sub_exist = SubResiler::where('user_id', $is_sub->id)->first();
                if($sub_exist) {
                    $get_res = User::find($sub_exist->res_id);
                    if($get_res) {
                        $is_sub->owner = $get_res->name;
                    }
                }
            }
            return Response()->json($users);
        }
  
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
    public function store(Request $request)
    {

        
        // dd($request->package_id);

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;


        request()->validate([
            'name' => 'required|unique:users',
            'password' => 'required',
            'email' => 'required|unique:users',
            'type' => 'required',
            'package_id' => 'required'
        ]);

        if($request->hasFile('image')){
            request()->validate([
                'image' => ['mimes:jpeg,jpg,png,gif'],
            ]);
        }

        // if ($request['host'] != null && !preg_match("/^([a-z]+\.){1,}+[a-z]+$/i",$request['host'])) {
        //     return response(['message'=>'The given data was invalid.', "errors"=>['host'=>['Invalid URl']]], 422);
        // }


        if ( ($request['host'] != "null" && $request['host'] != null && $request['host'] != '')  && !preg_match("/^([a-z0-9-]+\.){1,}+[a-z0-9]+$/i",$request['host'])) {
            return response(['message'=>'The given data was invalid.', "errors"=>['host'=>['Invalid URl']]], 422);
        }

        // $pack = "[$request->package_id]";


        if($request->hasFile('image')){
            $file = $request->file('image');
                $filename = rand(11111111, 99999999) .$file->getClientOriginalName();
                $path = '/var/www/vhosts/newpanel.kingiptv.pro/httpdocs/assets/img';
                $file->move($path, $filename);
                $request->image=$filename;
        }else {
            $filename = 'logo_reseller.png';
        }

        if($request->type == 'Admin'){

            $sld = '';
        }else {
            $sld = 0;
        }
        $last_user = User::orderBy('id', 'desc')->first();
        $new_id = (int)$last_user->id+1;
        // return response()->json($new_id, 404);
        $users = DB::connection('mysql2')->table('users')->where('member_id', $new_id)->get();
        do {            
            $users = DB::connection('mysql2')->table('users')->where('member_id', $new_id)->get();
            if(count($users) > 0) {
                $new_id += 500;
            }
        } while (count($users) > 0);
       
        $u = User::create([
            'id'                => $new_id,
            'name'              => $request['name'],
            'password'          => Hash::make($request['password']),
            'phone'             => $request['phone'] ,
            'solde'             => $sld,
            'email'             => $request['email'],
            'type'              => $request['type'],
            'image'             => $filename,
            'package_id'        => $request->package_id, 
            'host'              => $request['host'],
            'show_message'      => $request['show_message'] == true ? 1 : 0,
        ]);

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\User  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function show(User $User)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\User  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function edit(User $User)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // return response()->json(['message'=>'fdf'], 401);
        request()->validate([
            'name' => ['required', Rule::unique( 'users' )->ignore( $id )->whereNull('deleted_at')],
            'email' => ['required', 'string', 'email', Rule::unique( 'users' )->ignore( $id )->whereNull('deleted_at')],
            'type' => 'required',
            'package_id' => 'required'
        ]);

        if($request->hasFile('image')){
            request()->validate([
                'image' => ['mimes:jpeg,jpg,png,gif'],
            ]);
        }

        // dd($request->Newpassword);
        $user= User::findOrFail($id);

        if ( ($request['host'] != "null" && $request['host'] != null && $request['host'] != '')  && !preg_match("/^([a-z0-9-]+\.){1,}+[a-z0-9]+$/i",$request['host'])) {
            return response(['message'=>'The given data was invalid.', "errors"=>['host'=>['Invalid URl']]], 422);
        }

        if($request->hasFile('image')){
            $file = $request->file('image');
                $filename = rand(11111111, 99999999) .$file->getClientOriginalName();
                $path = '/var/www/vhosts/newpanel.kingiptv.pro/httpdocs/assets/img';
                $file->move($path, $filename);
                $request->image=$filename;
        }else {
                $filename = $user->image;
        }

        $current = $user->password;

        if($request->Newpassword  !== 'undefined') { 
            $pass = Hash::make($request->Newpassword);
        }else {
            $pass = $user->password;
        }

        // dd($request->password);

        // dd($pass);
    //    return response()->json($request['show_message'], 404);
        User::whereId($id)->update([
            'name'              => $request['name'],
            'password'          => $pass,
            'phone'             => $request['phone']=='null' ? '' : $request['phone'],
            'solde'             => $request['solde']=='null' ? '' : $request['solde'],
            'email'             => $request['email'],
            'type'              => $request['type'],
            'image'             => $filename,   
            'package_id'        => $request->package_id,
            'host'              => $request['host'] != "null" ? $request['host'] : '',  
            'show_message'      => ($request['show_message'] === true ||  $request['show_message'] === "true" || $request['show_message'] == 1) ? 1 : 0,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\User  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $User = User::findOrFail($id);
        $name = $User->name;
        $solde = $User->solde;
        $solde_test = $User->solde_test;
        $User->delete();

        $subRes = SubResiler::where('user_id', $id)->first();
        
        if($subRes) {
        	$subRes->delete();
        }

        ResellerStatistic::create([
            'reseller_id' => Auth::user()->id,
            'solde' => 0,
            'operation' => 2,
            'operation_name' => 'destroy_reseller',
            'slug' => $name . ' was deleted by ' . Auth::user()->name . '( Solde: ' . $solde . '), (Solde test : ' . $solde_test . ')'
        ]);
    }

    public function updateCredit(Request $request, $id)
    {

        $solde_current = DB::table('users')->where('id',$id)->get();

        
        // dd($request->solde + $solde_current[0]->solde);

        $user = Auth::user();
        $user_id = auth()->id();
        $user_solde = Auth::user()->solde;
        $user_solde_test = Auth::user()->solde_test;
        $user_gift = Auth::user()->gift;
        $user_type = Auth::user()->type;

        if($user_type == 'Admin'){
            $solde_current = DB::table('users')->where('id',$id)->get();

            User::whereId($id)->update([
                'solde'   => $request['solde'],
                'solde_test' => $request['soldeTest'],
                'gift' => $request['gift']
            ]);
            // + $solde_current[0]->solde)

            ResellerStatistic::create([
                'reseller_id' => $id,
                'solde' => $request->solde,
                'operation' => 1,
                'operation_name' => 'add_credit_to_reseller',
                'slug' => 'Admin added a credit to you'
            ]);
        }
        else {
            if(!($request->solde  > $user_solde) && $request['solde'] != 0){
                $solde_current = DB::table('users')->where('id',$id)->get();


                    $pp = User::whereId($id)->update([
                        'solde'   => $request['solde'] + $solde_current[0]->solde,
                    ]);

                    ResellerStatistic::create([
                        'reseller_id' => $user_id,
                        'solde' => $request->solde,
                        'operation' => 0,
                        'operation_name' => 'add_credit_to_sub_reseller',
                        'slug' => 'Add credit to sub reseller'
                    ]);

                    ResellerStatistic::create([
                        'reseller_id' => $id,
                        'solde' => $request->solde,
                        'operation' => 1,
                        'operation_name' => 'add_credit_to_sub_reseller',                       
                        'slug' => 'The reseller added a credit to you'
                    ]);

                    $user_res = User::whereId($user_id);
                    $user_res_solde = $user_solde - $request->solde ; 

                    User::whereId($user_id)->update([
                        'solde'   => $user_res_solde ,
                    ]);

                    // ResellerStatistic::create([
                    //     'reseller_id' => $user_id,
                    //     'solde' => $request->solde,
                    //     'operation' => 0,
                    // ]);
                    

            }

            if(!($request->soldeTest  > $user_solde_test) && $request['soldeTest'] != 0){
                $solde_current = DB::table('users')->where('id',$id)->get();


                    User::whereId($id)->update([
                        'solde_test'   => $request['soldeTest'] + $solde_current[0]->solde_test,
                    ]);

                    ResellerStatistic::create([
                        'reseller_id' => $user_id,
                        'solde' => $request->soldeTest,
                        'operation' => 0,
                        'operation_name' => 'add_credit_to_sub_reseller',
                        'slug' => 'Add test credit to sub reseller'
                    ]);

                    ResellerStatistic::create([
                        'reseller_id' => $id,
                        'solde' => $request->soldeTest,
                        'operation' => 1,
                        'operation_name' => 'add_credit_to_sub_reseller',
                        'slug' => 'The reseller added a test credit to you'
                    ]);

                    $user_res = User::whereId($user_id);
                    $user_res_solde_test = $user_solde_test - $request->soldeTest; 

                    User::whereId($user_id)->update([
                        'solde_test'   => $user_res_solde_test ,
                    ]);

                }
            }

            if(!($request->gift  > $user_gift) && $request['gift'] != 0){
                $solde_current = DB::table('users')->where('id',$id)->get();

                User::whereId($id)->update([
                    'gift'   => $request['gift'] + $solde_current[0]->gift,
                ]);

                ResellerStatistic::create([
                    'reseller_id' => $user_id,
                    'solde' => $request->gift,
                    'operation' => 0,
                    'operation_name' => 'add_credit_to_sub_reseller',
                    'slug' => 'Add gift to sub reseller'
                ]);

                ResellerStatistic::create([
                    'reseller_id' => $id,
                    'solde' => $request->gift,
                    'operation' => 1,
                    'operation_name' => 'add_credit_to_sub_reseller',
                    'slug' => 'The reseller added a gift to you'
                ]);

                $user_res = User::whereId($user_id);
                $user_res_gift = $user_gift - $request->gift; 

                User::whereId($user_id)->update([
                    'gift'   => $user_res_gift ,
                ]);

            }
            

    }

    public function recoverCredit(Request $request, $id)
    {

        $solde_current = DB::table('users')->where('id',$id)->get();

        
        // dd($request->solde + $solde_current[0]->solde);

        $user = Auth::user();
        $user_id = auth()->id();
        $user_solde = Auth::user()->solde;
        $user_solde_test = Auth::user()->solde_test;
        $user_gift = Auth::user()->gift;        
        $user_type = Auth::user()->type;

        $solde_current = DB::table('users')->where('id',$id)->get();

            if($request->solde <= $solde_current[0]->solde && $request['solde'] != 0){
                $solde_current = DB::table('users')->where('id',$id)->get();
                

                    $pp = User::whereId($id)->update([
                        'solde'   => $solde_current[0]->solde - $request->solde,
                    ]);

                    ResellerStatistic::create([
                        'reseller_id' => $user_id,
                        'solde' => $request->solde,
                        'operation' => 1,
                        'operation_name' => 'recover_credit_to_sub_reseller',
                        'slug' => 'The reseller recovered a credit'
                    ]);

                    ResellerStatistic::create([
                        'reseller_id' => $id,
                        'solde' => $request->solde,
                        'operation' => 0,
                        'operation_name' => 'recover_credit_to_sub_reseller',
                        'slug' => 'Credit Recovered by the reseller'
                    ]);

                    $user_res = User::whereId($user_id);
                    $user_res_solde = $user_solde + $request->solde ; 

                    User::whereId($user_id)->update([
                        'solde'   => $user_res_solde ,
                    ]);

                    // ResellerStatistic::create([
                    //     'reseller_id' => $user_id,
                    //     'solde' => $request->solde,
                    //     'operation' => 0,
                    // ]);
                    

            }

            if($request->soldeTest  <=  $solde_current[0]->solde_test && $request['soldeTest'] != 0){
                $solde_current = DB::table('users')->where('id',$id)->get();


                    User::whereId($id)->update([
                        'solde_test'   => $solde_current[0]->solde_test - $request['soldeTest'],
                    ]);

                    ResellerStatistic::create([
                        'reseller_id' => $user_id,
                        'solde' => $request->soldeTest,
                        'operation' => 1,
                        'operation_name' => 'recover_credit_to_sub_reseller',
                        'slug' => 'The reseller recovered a test credit'
                    ]);

                    ResellerStatistic::create([
                        'reseller_id' => $id,
                        'solde' => $request->soldeTest,
                        'operation' => 0,
                        'operation_name' => 'recover_credit_to_sub_reseller',                        
                        'slug' => 'Credit Recovered by the reseller'
                    ]);

                    $user_res = User::whereId($user_id);
                    $user_res_solde_test = $user_solde_test + $request->soldeTest; 

                    User::whereId($user_id)->update([
                        'solde_test'   => $user_res_solde_test ,
                    ]);

                    // ResellerStatistic::create([
                    //     'reseller_id' => $user_id,
                    //     'solde' => $request->solde,
                    //     'operation' => 0,
                    // ]);
                    

            }

            if($request->gift  <=  $solde_current[0]->gift && $request['gift'] != 0){
                $solde_current = DB::table('users')->where('id',$id)->get();


                    User::whereId($id)->update([
                        'gift'   => $solde_current[0]->gift - $request['gift'],
                    ]);

                    ResellerStatistic::create([
                        'reseller_id' => $user_id,
                        'solde' => $request->gift,
                        'operation' => 1,
                        'operation_name' => 'recover_gift_to_sub_reseller',
                        'slug' => 'The reseller recovered a test credit'
                    ]);

                    ResellerStatistic::create([
                        'reseller_id' => $id,
                        'solde' => $request->gift,
                        'operation' => 0,
                        'operation_name' => 'recover_gift_to_sub_reseller',                        
                        'slug' => 'Credit Recovered by the reseller'
                    ]);

                    $user_res = User::whereId($user_id);
                    $user_res_gift = $user_gift + $request->gift; 

                    User::whereId($user_id)->update([
                        'gift'   => $user_res_gift ,
                    ]);

                    // ResellerStatistic::create([
                    //     'reseller_id' => $user_id,
                    //     'solde' => $request->solde,
                    //     'operation' => 0,
                    // ]);
                    

            }
        
    }

    public function block(Request $request, $id)
    {
        $user = User::find($id);

        if($user->type != 'Admin') {
            User::whereId($id)->update([
                'blocked' => $user->password,
                'password' => Hash::make($request->password),
            ]);

            ActiveCode::where('user_id', $id)->update([
                'enabled' => 0 ,
                
            ]);
            MultiCode::where('user_id', $id)->update([
                'enabled' => 0 ,
                
            ]);
            MagDevice::where('user_id', $id)->update([
                'enabled' => 0 ,
                
            ]);
            MasterCode::where('user_id', $id)->update([
                'enabled' => 0 ,
                
            ]);
            DB::connection('mysql2')->table('users')->where('member_id' , $id)->update(
                [
                    'enabled' => 0,
                ]
            );
            DB::connection('mysql2')->table('users_activecode')->where('member_id' , $id)->update(
                [
                    'enabled' => 0,
                ]
            );

            $SubResiler = SubResiler::where('res_id', $id)->get();
            if($SubResiler) {
                foreach ($SubResiler as $sub) {
                    $sub_user = User::find($sub->user_id);
                    User::whereId($sub_user->id)->update([
                        'blocked' => $sub_user->password,
                        'password' => Hash::make($request->password),
                    ]);

                    ActiveCode::where('user_id', $sub_user->id)->update([
                        'enabled' => 0 ,
                        
                    ]);
                    MultiCode::where('user_id', $sub_user->id)->update([
                        'enabled' => 0 ,
                        
                    ]);
                    MagDevice::where('user_id', $sub_user->id)->update([
                        'enabled' => 0 ,
                        
                    ]);
                    MasterCode::where('user_id', $sub_user->id)->update([
                        'enabled' => 0 ,
                        
                    ]);
                    DB::connection('mysql2')->table('users')->where('member_id' , $sub_user->id)->update(
                        [
                            'enabled' => 0,
                        ]
                    );
                    DB::connection('mysql2')->table('users_activecode')->where('member_id' , $sub_user->id)->update(
                        [
                            'enabled' => 0,
                        ]
                    );

                    $SubResiler1 = SubResiler::where('res_id', $sub_user->id)->get();
                    if($SubResiler1) {
                        foreach ($SubResiler1 as $sub1) {
                            $sub_user1 = User::find($sub1->user_id);
                            User::whereId($sub_user1->id)->update([
                                'blocked' => $sub_user1->password,
                                'password' => Hash::make($request->password),
                            ]);

                            ActiveCode::where('user_id', $sub_user1->id)->update([
                                'enabled' => 0 ,
                                
                            ]);
                            MultiCode::where('user_id', $sub_user1->id)->update([
                                'enabled' => 0 ,
                                
                            ]);
                            MagDevice::where('user_id', $sub_user1->id)->update([
                                'enabled' => 0 ,
                                
                            ]);
                            MasterCode::where('user_id', $sub_user1->id)->update([
                                'enabled' => 0 ,
                                
                            ]);
                            DB::connection('mysql2')->table('users')->where('member_id' , $sub_user1->id)->update(
                                [
                                    'enabled' => 0,
                                ]
                            );
                            DB::connection('mysql2')->table('users_activecode')->where('member_id' , $sub_user1->id)->update(
                                [
                                    'enabled' => 0,
                                ]
                            );

                            $SubResiler2 = SubResiler::where('res_id', $sub_user1->id)->get();
                            if($SubResiler2) {
                                foreach ($SubResiler2 as $sub2) {
                                    $sub_user2 = User::find($sub2->user_id);
                                    User::whereId($sub_user2->id)->update([
                                        'blocked' => $sub_user2->password,
                                        'password' => Hash::make($request->password),
                                    ]);

                                    ActiveCode::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 0 ,
                                        
                                    ]);
                                    MultiCode::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 0 ,
                                        
                                    ]);
                                    MagDevice::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 0 ,
                                        
                                    ]);
                                    MasterCode::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 0 ,
                                        
                                    ]);
                                    DB::connection('mysql2')->table('users')->where('member_id' , $sub_user2->id)->update(
                                        [
                                            'enabled' => 0,
                                        ]
                                    );
                                    DB::connection('mysql2')->table('users_activecode')->where('member_id' , $sub_user2->id)->update(
                                        [
                                            'enabled' => 0,
                                        ]
                                    );

                                    $SubResiler3 = SubResiler::where('res_id', $sub_user2->id)->get();
                                    if($SubResiler3) {
                                        foreach ($SubResiler3 as $sub3) {
                                            $sub_user3 = User::find($sub3->user_id);
                                            User::whereId($sub_user3->id)->update([
                                                'blocked' => $sub_user3->password,
                                                'password' => Hash::make($request->password),
                                            ]);

                                            ActiveCode::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 0 ,
                                                
                                            ]);
                                            MultiCode::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 0 ,
                                                
                                            ]);
                                            MagDevice::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 0 ,
                                                
                                            ]);
                                            MasterCode::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 0 ,
                                                
                                            ]);
                                            DB::connection('mysql2')->table('users')->where('member_id' , $sub_user3->id)->update(
                                                [
                                                    'enabled' => 0,
                                                ]
                                            );
                                            DB::connection('mysql2')->table('users_activecode')->where('member_id' , $sub_user3->id)->update(
                                                [
                                                    'enabled' => 0,
                                                ]
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
        }
    }

    public function unblock($id)
    {

        $user = User::find($id);

        if($user->type != 'Admin') {
            User::whereId($id)->update([
                'blocked' => '',
                'password' => $user->blocked
            ]);

            ActiveCode::where('user_id', $id)->update([
                'enabled' => 1 ,
                
            ]);
            MultiCode::where('user_id', $id)->update([
                'enabled' => 1 ,
                
            ]);
            MagDevice::where('user_id', $id)->update([
                'enabled' => 1 ,
                
            ]);
            MasterCode::where('user_id', $id)->update([
                'enabled' => 1 ,
                
            ]);
            DB::connection('mysql2')->table('users')->where('member_id' , $id)->update(
                [
                    'enabled' => 1,
                ]
            );
            DB::connection('mysql2')->table('users_activecode')->where('member_id' , $id)->update(
                [
                    'enabled' => 1,
                ]
            );

            $SubResiler = SubResiler::where('res_id', $id)->get();
            if($SubResiler) {
                foreach ($SubResiler as $sub) {
                    $sub_user = User::find($sub->user_id);
                    User::whereId($sub_user->id)->update([
                        'blocked' => '',
                        'password' => $sub_user->blocked
                    ]);

                    ActiveCode::where('user_id', $sub_user->id)->update([
                        'enabled' => 1 ,
                        
                    ]);
                    MultiCode::where('user_id', $sub_user->id)->update([
                        'enabled' => 1 ,
                        
                    ]);
                    MagDevice::where('user_id', $sub_user->id)->update([
                        'enabled' => 1 ,
                        
                    ]);
                    MasterCode::where('user_id', $sub_user->id)->update([
                        'enabled' => 1 ,
                        
                    ]);
                    DB::connection('mysql2')->table('users')->where('member_id' , $sub_user->id)->update(
                        [
                            'enabled' => 1,
                        ]
                    );
                    DB::connection('mysql2')->table('users_activecode')->where('member_id' , $sub_user->id)->update(
                        [
                            'enabled' => 1,
                        ]
                    );

                    $SubResiler1 = SubResiler::where('res_id', $sub_user->id)->get();
                    if($SubResiler1) {
                        foreach ($SubResiler1 as $sub1) {
                            $sub_user1 = User::find($sub1->user_id);
                            User::whereId($sub_user1->id)->update([
                                'blocked' => '',
                                'password' => $sub_user1->blocked
                            ]);

                            ActiveCode::where('user_id', $sub_user1->id)->update([
                                'enabled' => 1 ,
                                
                            ]);
                            MultiCode::where('user_id', $sub_user1->id)->update([
                                'enabled' => 1 ,
                                
                            ]);
                            MagDevice::where('user_id', $sub_user1->id)->update([
                                'enabled' => 1 ,
                                
                            ]);
                            MasterCode::where('user_id', $sub_user1->id)->update([
                                'enabled' => 1 ,
                                
                            ]);
                            DB::connection('mysql2')->table('users')->where('member_id' , $sub_user1->id)->update(
                                [
                                    'enabled' => 1,
                                ]
                            );
                            DB::connection('mysql2')->table('users_activecode')->where('member_id' , $sub_user1->id)->update(
                                [
                                    'enabled' => 1,
                                ]
                            );

                            $SubResiler2 = SubResiler::where('res_id', $sub_user1->id)->get();
                            if($SubResiler2) {
                                foreach ($SubResiler2 as $sub2) {
                                    $sub_user2 = User::find($sub2->user_id);
                                    User::whereId($sub_user2->id)->update([
                                        'blocked' => '',
                                        'password' => $sub_user2->blocked
                                    ]);
                                    
                                    ActiveCode::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 1 ,
                                        
                                    ]);
                                    MultiCode::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 1 ,
                                        
                                    ]);
                                    MagDevice::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 1 ,
                                        
                                    ]);
                                    MasterCode::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 1 ,
                                        
                                    ]);
                                    DB::connection('mysql2')->table('users')->where('member_id' , $sub_user2->id)->update(
                                        [
                                            'enabled' => 1,
                                        ]
                                    );
                                    DB::connection('mysql2')->table('users_activecode')->where('member_id' , $sub_user2->id)->update(
                                        [
                                            'enabled' => 1,
                                        ]
                                    );

                                    $SubResiler3 = SubResiler::where('res_id', $sub_user2->id)->get();
                                    if($SubResiler3) {
                                        foreach ($SubResiler3 as $sub3) {
                                            $sub_user3 = User::find($sub3->user_id);
                                            User::whereId($sub_user3->id)->update([
                                                'blocked' => '',
                                                'password' => $sub_user3->blocked
                                            ]);

                                            ActiveCode::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 1 ,
                                                
                                            ]);
                                            MultiCode::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 1 ,
                                                
                                            ]);
                                            MagDevice::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 1 ,
                                                
                                            ]);
                                            MasterCode::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 1 ,
                                                
                                            ]);
                                            DB::connection('mysql2')->table('users')->where('member_id' , $sub_user3->id)->update(
                                                [
                                                    'enabled' => 1,
                                                ]
                                            );
                                            DB::connection('mysql2')->table('users_activecode')->where('member_id' , $sub_user3->id)->update(
                                                [
                                                    'enabled' => 1,
                                                ]
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
        }
    }


    public function CreateSubResiler(Request $request)
    {
        // dd($request->id);

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        request()->validate([
            'name' => 'required|unique:users',
            'password' => 'required',
            'email' => 'required|unique:users',
            'package_id' => 'required',
        ]);

        if($request->hasFile('image')){
            $file = $request->file('image');
                $filename = rand(11111111, 99999999) .$file->getClientOriginalName();
                $path = '/var/www/vhosts/newpanel.kingiptv.pro/httpdocs/assets/img';
                $file->move($path, $filename);
                $request->image=$filename;
        }else {
            $filename = 'logo_reseller.png';
        }
       
        // if($user_type == 'Admin'){

        //     $type = $request->type;
        // }else {
        //     $type = 'SubResiler';
        // }


        $user = new User(); 
            $user->name             =  $request->name;
            $user->password         =  Hash::make($request->password);
            $user->email            =  $request->email;
            $user->type             =  'SubResiler';
            $user->phone            =  $request->phone;
            $user->solde            =  0;
            $user->image            =  $filename;
            $user->package_id       =  $request->package_id;
            $user->host             =  Auth::user()->host;
            $user->show_message     =  ($request['show_message'] === true ||  $request['show_message'] === "true" || $request['show_message'] == 1) ? 1 : 0;

        $user->save();

        $insertedId = $user->id;
        // dd($insertedId);

        $SubResiler = new SubResiler(); 

            $SubResiler->user_id        =  $insertedId;
            $SubResiler->res_id         =  $user_id;
           
        $SubResiler->save();

    }


    public function updateProfile(Request $request, $id)
    {
        request()->validate([
            'image' => ['nullable', 'mimes:jpeg,jpg,png,gif'],
        ]);
        $packages = explode(',', $request->packages);
        // dd($request->Newpassword);
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        // dd($request->all());
        $user= User::findOrFail($user_id);

        if($request->hasFile('image')){
            $file = $request->file('image');
                $filename = rand(11111111, 99999999) .$file->getClientOriginalName();
                $path = '/var/www/vhosts/newpanel.kingiptv.pro/httpdocs/assets/img';
                $file->move($path, $filename);
                $request->image=$filename;
        }else {
                $filename = $user->image;
        }

        $current = $user->password;

        if($request->Newpassword !== null) { 
            $pass = Hash::make($request->Newpassword);
        }else {
            $pass = $user->password;
        }

        // dd($request->password);

        // dd($pass);
        $i = 1;
        foreach ($packages as $package) {
            $p = Package::where('package_id', $package)->where('user_id', $id)->first();
            if($p) {
                $p->order = $i;
                $p->save();
            }else{
                Package::create([
                    'user_id'           => $id,
                    'package_id'        => $package,
                    'order'             => $i,  
                ]);
            }
            $i++;
        }
       
        User::whereId($id)->update([
            'name'              => $request['name'],
            'password'          => $pass,
            'phone'             => $request['phone']=='null' ? '' : $request['phone'],
            'solde'             => $user->solde,
            'email'             => $request['email'],
            'type'              => $user->type,
            'image'             => $filename,   
        ]);
    }

    public function GetAllUsers (){

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        if($user_type == 'Admin'){
            // $allusers =  DB::table('users')
            // ->where('users.id', '!=' , $user_id)
            // ->get();
            $allusers =  DB::table('users')
            ->where('users.id', '!=' , $user_id)
            // ->select('blocked', 'created_at', 'deleted_at', 'email', 'email_verified_at', 'host', 'id', 'image', 'name', 'package_id', 'solde', 'phone', 'remember_token', 'show_message', 'solde_test', 'type', 'updated_at')
            ->select('name', 'id')
            ->get();
            return Response()->json($allusers);

        }else if($user_type == 'Resiler'){
            // $allusers = DB::table('users')
            // ->where('type', '!=', 'Admin')
            // ->get();
            $allusers = DB::table('users')
            ->where('type', '!=', 'Admin')
            ->select('name', 'id')
            ->get();
            return Response()->json($allusers);

         }else {
            $allusers = DB::table('users')
            ->join('sub_resilers', 'users.id', '=', 'sub_resilers.res_id')
            ->where('sub_resilers.user_id', '=' , $user_id)
            ->select('name')
            ->get();

            $getadmin = DB::table('users')
            ->where('type', '=', 'Admin')
            ->select('name', 'id')
            ->get();

            $merged = $allusers->merge($getadmin);

            $result = $merged->all();

            return Response()->json($result);
         }

    }

    public function selectPack(){

        $packs= [];
        $dd = DB::connection('mysql2')->table('packages')->select('packages.*')->get();

        foreach($dd as $pk){

            $tt['text'] = $pk->package_name;
            $tt['value'] = $pk->id; 
            $packs[] = $tt;
        }      

        
        return Response()->json($packs);
    }

    public function getStatistic($id, $date)
    {
        $today = Carbon::today();
        // $getResStatistic = DB::table('reseller_statistics')->select('operation_name', DB::raw('count(solde) as solde'))->groupBy('operation_name')->get();
        if($date == 1) {
            $getResStatistic = DB::table('reseller_statistics')->where('reseller_id', $id)->whereDate('created_at', Carbon::today())->select('operation_name', DB::raw('count(solde) as solde'))->groupBy('operation_name')->get();
        }else {
            if($date == 2) {
                $getResStatistic = DB::table('reseller_statistics')->where('reseller_id', $id)->whereDate('created_at', '>=', Carbon::yesterday())->select('operation_name', DB::raw('count(solde) as solde'))->groupBy('operation_name')->get();
            }else {
                if($date == 7) {
                    $getResStatistic = DB::table('reseller_statistics')->where('reseller_id', $id)->whereDate('created_at', '>', Carbon::today()->subDays(7))->select('operation_name', DB::raw('count(solde) as solde'))->groupBy('operation_name')->get();
                }
            }
        }
        $data = [0,0,0,0];

        foreach ($getResStatistic as $value) {
            if($value->operation_name == 'active_code') { $data[0] += $value->solde; }
            else{
                if($value->operation_name == 'user') { $data[1] = $value->solde; }
                else{
                    if($value->operation_name == 'mass_code') { $data[0] += $value->solde; }
                    else {
                        if($value->operation_name == 'mag_device') { $data[2] = $value->solde; }
                        else {
                            if($value->operation_name == 'add_credit_to_sub_reseller') { $data[3] = $value->solde; }
                        }
                    }
                }
            }
        }
        

        return response()->json($data);
    }

    public function mySubRes(Request $request) {
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        if($user_type == 'Admin') {
            $subRes = DB::table('users')
            ->select('name', 'id')
            ->get();
            foreach ($subRes as $s) {
                $s->user = $s->name;
                $s->user_id = $s->id;
            }
        }else{
            $subRes = SubResiler::where('res_id', $user_id)->get();
            foreach ($subRes as $s) {
                $user = User::find($s->user_id);
                $s->user = $user ? $user->name : '';
            }
        }
        
        
        return response()->json($subRes);        
    }

    public function arcplayer(Request $request) {
                
    }

}
