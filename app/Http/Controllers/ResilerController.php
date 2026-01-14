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
use GuzzleHttp\Client;

use DB;

class ResilerController extends Controller
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

        // return ActiveCode::find( $user_id )->latest()->paginate( 10 );
        if ($user_type !== 'Admin') {

            $model = new SubResiler;
            $SubResiler = $model;

            // $SubResiler =  DB::table( 'sub_resilers' )
            $SubResiler = $SubResiler->join('users', 'sub_resilers.user_id', '=', 'users.id');
            $SubResiler = $SubResiler->select('users.*');
            $SubResiler = $SubResiler->where('sub_resilers.res_id', '=', $user_id);
            $SubResiler = $SubResiler->where('users.deleted_at', null);
            if ($query)
                $SubResiler = $SubResiler->where('users.name', 'LIKE', "%{$query}%");
            $SubResiler = $SubResiler->orderBy('id', 'ASC')->paginate(20);

            // dd( $users );
            return Response()->json($SubResiler);

        } else {

            $dd = new User;
            $users = $dd;

            $users = $users->select('users.*');
            $users = $users->where('deleted_at', null);
            if ($query)
                $users = $users->where('users.name', 'LIKE', "%{$query}%");

            $users = $users->orderBy('id', 'ASC')->paginate(20);

            foreach ($users as $is_sub) {
                $is_sub->owner = '';
                $is_sub->owner_id = '';
                $sub_exist = SubResiler::where('user_id', $is_sub->id)->first();
                if ($sub_exist) {
                    $get_res = User::find($sub_exist->res_id);
                    if ($get_res) {
                        $is_sub->owner = $get_res->name;
                        $is_sub->owner_id = $get_res->id;
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

        // dd( $request->package_id );

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

        if ($request->hasFile('image')) {
            request()->validate([
                'image' => ['mimes:jpeg,jpg,png,gif'],
            ]);
        }

        if (($request['host'] != 'null' && $request['host'] != null && $request['host'] != '') && !preg_match("/^([a-z0-9-]+\.){1,}+[a-z0-9]+$/i", $request['host'])) {
            return response(['message' => 'The given data was invalid.', 'errors' => ['host' => ['Invalid URl']]], 422);
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = rand(11111111, 99999999) . $file->getClientOriginalName();
            $path = '/home/admin/domains/newpanel.kingiptv.pro/public_html/assets/img';
            $file->move($path, $filename);
            $request->image = $filename;
        } else {
            $filename = 'logo_reseller.png';
        }

        if ($request->type == 'Admin') {

            $sld = '';
        } else {
            $sld = 0;
        }
        $last_user = User::orderBy('id', 'desc')->first();
        $new_id = (int) $last_user->id + 1;
        $users = DB::connection('mysql2')->table('users')->where('member_id', $new_id)->get();
        do {

            $users = DB::connection('mysql2')->table('users')->where('member_id', $new_id)->get();
            if (count($users) > 0) {
                $new_id += 500;
            }
        }
        while (count($users) > 0);

        $u = User::create([
            'id' => $new_id,
            'name' => $request['name'],
            'password' => Hash::make($request['password']),
            'phone' => $request['phone'],
            'solde' => $sld,
            'email' => $request['email'],
            'type' => $request['type'],
            'image' => $filename,
            'package_id' => $request->package_id,
            'host' => $request['host'],
            'show_message' => $request['show_message'] == true ? 1 : 0,
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

        if (Auth::user()->type != 'Admin') {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);
            $subRes = SubResiler::whereIn('res_id', $res)->where('user_id', $id)->first();
            if ($subRes) {
            } else {
                return response(['message' => 'Wrong user'], 402);
            }
        }
        request()->validate([
            'name' => ['required'],
            'email' => ['required', 'string', 'email'],
            'type' => 'required',
            'package_id' => 'required'
        ]);

        if ($request->hasFile('image')) {
            request()->validate([
                'image' => ['mimes:jpeg,jpg,png,gif'],
            ]);
        }

        $user = User::findOrFail($id);

        if (($request['host'] != 'null' && $request['host'] != null && $request['host'] != '') && !preg_match("/^([a-z0-9-]+\.){1,}+[a-z0-9]+$/i", $request['host'])) {
            return response(['message' => 'The given data was invalid.', 'errors' => ['host' => ['Invalid URl']]], 422);
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = rand(11111111, 99999999) . $file->getClientOriginalName();
            $path = '/home/admin/domains/newpanel.kingiptv.pro/public_html/assets/img';
            $file->move($path, $filename);
            $request->image = $filename;
        } else {
            $filename = $user->image;
        }

        $current = $user->password;

        if ($request->Newpassword !== 'undefined') {

            $pass = Hash::make($request->Newpassword);
        } else {
            $pass = $user->password;
        }

        User::whereId($id)->update([
            'name' => $request['name'],
            'password' => $pass,
            'phone' => $request['phone'] == 'null' ? '' : $request['phone'],
            'email' => $request['email'],
            'type' => Auth::user()->type != 'Admin' ? 'SubResiler' : $request['type'],
            'image' => $filename,
            'package_id' => $request->package_id,
            'host' => $request['host'] != 'null' ? $request['host'] : '',
            'show_message' => ($request['show_message'] === true || $request['show_message'] === 'true' || $request['show_message'] == 1) ? 1 : 0,
        ]);

        $subRes = SubResiler::where('res_id', $id)->get();
        foreach ($subRes as $row) {
            User::whereId($row->user_id)->update([
                'host' => $request['host'] != 'null' ? $request['host'] : ''
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\User  $activeCode
     * @return \Illuminate\Http\Response
     */

    public function destroy($id)
    {

        if (Auth::user()->type != 'Admin') {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);
            $subRes = SubResiler::whereIn('res_id', $res)->where('user_id', $id)->first();
            if ($subRes) {
            } else {
                return response(['message' => 'Wrong user'], 402);
            }
        }

        $User = User::findOrFail($id);
        $name = $User->name;
        $solde = $User->solde;
        $solde_test = $User->solde_test;
        $User->delete();

        $subRes = SubResiler::where('user_id', $id)->first();

        if ($subRes) {
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

        if (Auth::user()->type != 'Admin') {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);
            $subRes = SubResiler::whereIn('res_id', $res)->where('user_id', $id)->first();
            if ($subRes) {
            } else {
                return response(['message' => 'Wrong user'], 402);
            }
        }

        $solde_current = DB::table('users')->where('id', $id)->get();

        // dd( $request->solde + $solde_current[ 0 ]->solde );

        $user = Auth::user();
        $user_id = auth()->id();
        $user_solde = Auth::user()->solde;
        $user_solde_test = Auth::user()->solde_test;
        $user_solde_app = Auth::user()->solde_app;
        $user_gift = Auth::user()->gift;
        $user_type = Auth::user()->type;
        $request->solde = \str_replace(' ', '', $request->solde);
        $request->soldeTest = \str_replace(' ', '', $request->soldeTest);
        $request->soldeApp = \str_replace(' ', '', $request->soldeApp);
        $request->gift = \str_replace(' ', '', $request->gift);

        if ($request->solde < 0 || $request->soldeTest < 0) {
            return response()->json(['msg' => 'the credit must be positive'], 500);
        }

        if ($user_type == 'Admin') {
            $solde_current = DB::table('users')->where('id', $id)->get();

            User::whereId($id)->update([
                'solde' => \str_replace(' ', '', $request->solde),
                'solde_test' => \str_replace(' ', '', $request->soldeTest),
                'solde_app' => \str_replace(' ', '', $request->soldeApp),
                'gift' => \str_replace(' ', '', $request->gift),
            ]);
            // + $solde_current[ 0 ]->solde )

            ResellerStatistic::create([
                'reseller_id' => $id,
                'admin_id' => Auth::user()->id,
                'solde' => $request->solde,
                'operation' => 1,
                'operation_name' => 'add_credit_to_reseller',
                'slug' => 'Admin added a credit to you'
            ]);
        } else {
            if (!($request->solde > $user_solde) && $request->solde != 0) {
                $solde_current = DB::table('users')->where('id', $id)->get();

                $pp = User::whereId($id)->update([
                    'solde' => $request->solde + $solde_current[0]->solde,
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
                $user_res_solde = $user_solde - $request->solde;

                User::whereId($user_id)->update([
                    'solde' => $user_res_solde,
                ]);

                // ResellerStatistic::create( [
                //     'reseller_id' => $user_id,
                //     'solde' => $request->solde,
                //     'operation' => 0,
                // ] );

            }

            if (!($request->soldeTest > $user_solde_test) && $request->soldeTest != 0) {
                $solde_current = DB::table('users')->where('id', $id)->get();

                User::whereId($id)->update([
                    'solde_test' => $request->soldeTest + $solde_current[0]->solde_test,
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
                    'solde_test' => $user_res_solde_test,
                ]);
            }

            if (!($request->soldeApp > $user_solde_app) && $request->soldeApp != 0) {
                $solde_current = DB::table('users')->where('id', $id)->get();

                User::whereId($id)->update([
                    'solde_app' => $request->soldeApp + $solde_current[0]->solde_app,
                ]);

                ResellerStatistic::create([
                    'reseller_id' => $user_id,
                    'solde' => $request->soldeApp,
                    'operation' => 0,
                    'operation_name' => 'add_credit_to_sub_reseller',
                    'slug' => 'Add application credit to sub reseller'
                ]);

                ResellerStatistic::create([
                    'reseller_id' => $id,
                    'solde' => $request->soldeApp,
                    'operation' => 1,
                    'operation_name' => 'add_credit_to_sub_reseller',
                    'slug' => 'The reseller added a application credit to you'
                ]);

                $user_res = User::whereId($user_id);
                $user_res_solde_app = $user_solde_app - $request->soldeApp;

                User::whereId($user_id)->update([
                    'solde_app' => $user_res_solde_app,
                ]);
            }

            if (!($request->gift > $user_gift) && $request->gift != 0) {
                $solde_current = DB::table('users')->where('id', $id)->get();

                User::whereId($id)->update([
                    'gift' => $request->gift + $solde_current[0]->gift,
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
                    'gift' => $user_res_gift,
                ]);
            }
        }

    }

    public function recoverCredit(Request $request, $id)
    {
        // Authorization check
        if (Auth::user()->type != 'Admin') {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);
            $subRes = SubResiler::whereIn('res_id', $res)->where('user_id', $id)->first();
            if (!$subRes) {
                return response(['message' => 'Wrong user'], 402);
            }
        }

        // Input validation
        $request->solde = floatval(str_replace(' ', '', $request->solde ?? 0));
        $request->soldeTest = floatval(str_replace(' ', '', $request->soldeTest ?? 0));
        $request->soldeApp = floatval(str_replace(' ', '', $request->soldeApp ?? 0));
        $request->gift = floatval(str_replace(' ', '', $request->gift ?? 0));

        if ($request->solde < 0 || $request->soldeTest < 0 || $request->soldeApp < 0 || $request->gift < 0) {
            return response()->json(['msg' => 'the credit must be positive'], 400);
        }

        $user_id = auth()->id();

        // Use transaction with row locking to prevent race conditions
        DB::beginTransaction();
        try {
            // Lock both user rows to prevent concurrent modifications
            $targetUser = User::where('id', $id)->lockForUpdate()->first();
            $currentUser = User::where('id', $user_id)->lockForUpdate()->first();

            if (!$targetUser || !$currentUser) {
                DB::rollback();
                return response()->json(['msg' => 'User not found'], 404);
            }

            // Recover solde credit
            if ($request->solde > 0 && $request->solde <= $targetUser->solde) {
                $targetUser->solde -= $request->solde;
                $currentUser->solde += $request->solde;

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
            }

            // Recover test credit
            if ($request->soldeTest > 0 && $request->soldeTest <= $targetUser->solde_test) {
                $targetUser->solde_test -= $request->soldeTest;
                $currentUser->solde_test += $request->soldeTest;

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
                    'slug' => 'Test Credit Recovered by the reseller'
                ]);
            }

            // Recover app credit
            if ($request->soldeApp > 0 && $request->soldeApp <= $targetUser->solde_app) {
                $targetUser->solde_app -= $request->soldeApp;
                $currentUser->solde_app += $request->soldeApp;

                ResellerStatistic::create([
                    'reseller_id' => $user_id,
                    'solde' => $request->soldeApp,
                    'operation' => 1,
                    'operation_name' => 'recover_credit_to_sub_reseller',
                    'slug' => 'The reseller recovered a application credit'
                ]);

                ResellerStatistic::create([
                    'reseller_id' => $id,
                    'solde' => $request->soldeApp,
                    'operation' => 0,
                    'operation_name' => 'recover_credit_to_sub_reseller',
                    'slug' => 'Application credit Recovered by the reseller'
                ]);
            }

            // Recover gift credit
            if ($request->gift > 0 && $request->gift <= $targetUser->gift) {
                $targetUser->gift -= $request->gift;
                $currentUser->gift += $request->gift;

                ResellerStatistic::create([
                    'reseller_id' => $user_id,
                    'solde' => $request->gift,
                    'operation' => 1,
                    'operation_name' => 'recover_credit_to_sub_reseller',
                    'slug' => 'The reseller recovered a gift credit'
                ]);

                ResellerStatistic::create([
                    'reseller_id' => $id,
                    'solde' => $request->gift,
                    'operation' => 0,
                    'operation_name' => 'recover_credit_to_sub_reseller',
                    'slug' => 'Gift credit Recovered by the reseller'
                ]);
            }

            // Save both users atomically
            $targetUser->save();
            $currentUser->save();

            DB::commit();
            return response()->json(['msg' => 'Credit recovered successfully'], 200);

        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['msg' => 'Transaction failed: ' . $th->getMessage()], 500);
        }
    }

    public function block(Request $request, $id)
    {

        if (Auth::user()->type != 'Admin') {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);
            $subRes = SubResiler::whereIn('res_id', $res)->where('user_id', $id)->first();
            if ($subRes) {
            } else {
                return response(['message' => 'Wrong user'], 402);
            }
        }

        $user = User::find($id);

        if ($user->type != 'Admin') {
            User::whereId($id)->update([
                'blocked' => $user->password,
                'password' => Hash::make($request->password),
            ]);

            ActiveCode::where('user_id', $id)->update([
                'enabled' => 0,

            ]);
            MultiCode::where('user_id', $id)->update([
                'enabled' => 0,

            ]);
            MagDevice::where('user_id', $id)->update([
                'enabled' => 0,

            ]);
            MasterCode::where('user_id', $id)->update([
                'enabled' => 0,

            ]);
            DB::connection('mysql2')->table('users')->where('member_id', $id)->update(
                [
                    'enabled' => 0,
                ]
            );
            DB::connection('mysql2')->table('users_activecode')->where('member_id', $id)->update(
                [
                    'enabled' => 0,
                ]
            );

            $SubResiler = SubResiler::where('res_id', $id)->get();
            if ($SubResiler) {
                foreach ($SubResiler as $sub) {
                    $sub_user = User::find($sub->user_id);
                    User::whereId($sub_user->id)->update([
                        'blocked' => $sub_user->password,
                        'password' => Hash::make($request->password),
                    ]);

                    ActiveCode::where('user_id', $sub_user->id)->update([
                        'enabled' => 0,

                    ]);
                    MultiCode::where('user_id', $sub_user->id)->update([
                        'enabled' => 0,

                    ]);
                    MagDevice::where('user_id', $sub_user->id)->update([
                        'enabled' => 0,

                    ]);
                    MasterCode::where('user_id', $sub_user->id)->update([
                        'enabled' => 0,

                    ]);
                    DB::connection('mysql2')->table('users')->where('member_id', $sub_user->id)->update(
                        [
                            'enabled' => 0,
                        ]
                    );
                    DB::connection('mysql2')->table('users_activecode')->where('member_id', $sub_user->id)->update(
                        [
                            'enabled' => 0,
                        ]
                    );

                    $SubResiler1 = SubResiler::where('res_id', $sub_user->id)->get();
                    if ($SubResiler1) {
                        foreach ($SubResiler1 as $sub1) {
                            $sub_user1 = User::find($sub1->user_id);
                            User::whereId($sub_user1->id)->update([
                                'blocked' => $sub_user1->password,
                                'password' => Hash::make($request->password),
                            ]);

                            ActiveCode::where('user_id', $sub_user1->id)->update([
                                'enabled' => 0,

                            ]);
                            MultiCode::where('user_id', $sub_user1->id)->update([
                                'enabled' => 0,

                            ]);
                            MagDevice::where('user_id', $sub_user1->id)->update([
                                'enabled' => 0,

                            ]);
                            MasterCode::where('user_id', $sub_user1->id)->update([
                                'enabled' => 0,

                            ]);
                            DB::connection('mysql2')->table('users')->where('member_id', $sub_user1->id)->update(
                                [
                                    'enabled' => 0,
                                ]
                            );
                            DB::connection('mysql2')->table('users_activecode')->where('member_id', $sub_user1->id)->update(
                                [
                                    'enabled' => 0,
                                ]
                            );

                            $SubResiler2 = SubResiler::where('res_id', $sub_user1->id)->get();
                            if ($SubResiler2) {
                                foreach ($SubResiler2 as $sub2) {
                                    $sub_user2 = User::find($sub2->user_id);
                                    User::whereId($sub_user2->id)->update([
                                        'blocked' => $sub_user2->password,
                                        'password' => Hash::make($request->password),
                                    ]);

                                    ActiveCode::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 0,

                                    ]);
                                    MultiCode::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 0,

                                    ]);
                                    MagDevice::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 0,

                                    ]);
                                    MasterCode::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 0,

                                    ]);
                                    DB::connection('mysql2')->table('users')->where('member_id', $sub_user2->id)->update(
                                        [
                                            'enabled' => 0,
                                        ]
                                    );
                                    DB::connection('mysql2')->table('users_activecode')->where('member_id', $sub_user2->id)->update(
                                        [
                                            'enabled' => 0,
                                        ]
                                    );

                                    $SubResiler3 = SubResiler::where('res_id', $sub_user2->id)->get();
                                    if ($SubResiler3) {
                                        foreach ($SubResiler3 as $sub3) {
                                            $sub_user3 = User::find($sub3->user_id);
                                            User::whereId($sub_user3->id)->update([
                                                'blocked' => $sub_user3->password,
                                                'password' => Hash::make($request->password),
                                            ]);

                                            ActiveCode::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 0,

                                            ]);
                                            MultiCode::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 0,

                                            ]);
                                            MagDevice::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 0,

                                            ]);
                                            MasterCode::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 0,

                                            ]);
                                            DB::connection('mysql2')->table('users')->where('member_id', $sub_user3->id)->update(
                                                [
                                                    'enabled' => 0,
                                                ]
                                            );
                                            DB::connection('mysql2')->table('users_activecode')->where('member_id', $sub_user3->id)->update(
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

        if (Auth::user()->type != 'Admin') {
            $res = SubResiler::where('res_id', Auth::user()->id)->pluck('user_id')->toArray();
            array_push($res, Auth::user()->id);
            $subRes = SubResiler::whereIn('res_id', $res)->whereIn('user_id', $res)->first();
            if ($subRes) {
            } else {
                return response(['message' => 'Wrong user'], 402);
            }
        }

        $user = User::find($id);

        if ($user->type != 'Admin') {
            User::whereId($id)->update([
                'password' => $user->blocked,
                'blocked' => ''
            ]);

            ActiveCode::where('user_id', $id)->update([
                'enabled' => 1,

            ]);
            MultiCode::where('user_id', $id)->update([
                'enabled' => 1,

            ]);
            MagDevice::where('user_id', $id)->update([
                'enabled' => 1,

            ]);
            MasterCode::where('user_id', $id)->update([
                'enabled' => 1,

            ]);
            DB::connection('mysql2')->table('users')->where('member_id', $id)->update(
                [
                    'enabled' => 1,
                ]
            );
            DB::connection('mysql2')->table('users_activecode')->where('member_id', $id)->update(
                [
                    'enabled' => 1,
                ]
            );

            $SubResiler = SubResiler::where('res_id', $id)->get();
            if ($SubResiler) {
                foreach ($SubResiler as $sub) {
                    $sub_user = User::find($sub->user_id);
                    User::whereId($sub_user->id)->update([
                        'password' => $sub_user->blocked,
                        'blocked' => '',
                    ]);

                    ActiveCode::where('user_id', $sub_user->id)->update([
                        'enabled' => 1,

                    ]);
                    MultiCode::where('user_id', $sub_user->id)->update([
                        'enabled' => 1,

                    ]);
                    MagDevice::where('user_id', $sub_user->id)->update([
                        'enabled' => 1,

                    ]);
                    MasterCode::where('user_id', $sub_user->id)->update([
                        'enabled' => 1,

                    ]);
                    DB::connection('mysql2')->table('users')->where('member_id', $sub_user->id)->update(
                        [
                            'enabled' => 1,
                        ]
                    );
                    DB::connection('mysql2')->table('users_activecode')->where('member_id', $sub_user->id)->update(
                        [
                            'enabled' => 1,
                        ]
                    );

                    $SubResiler1 = SubResiler::where('res_id', $sub_user->id)->get();
                    if ($SubResiler1) {
                        foreach ($SubResiler1 as $sub1) {
                            $sub_user1 = User::find($sub1->user_id);
                            User::whereId($sub_user1->id)->update([
                                'password' => $sub_user1->blocked,
                                'blocked' => ''

                            ]);

                            ActiveCode::where('user_id', $sub_user1->id)->update([
                                'enabled' => 1,

                            ]);
                            MultiCode::where('user_id', $sub_user1->id)->update([
                                'enabled' => 1,

                            ]);
                            MagDevice::where('user_id', $sub_user1->id)->update([
                                'enabled' => 1,

                            ]);
                            MasterCode::where('user_id', $sub_user1->id)->update([
                                'enabled' => 1,

                            ]);
                            DB::connection('mysql2')->table('users')->where('member_id', $sub_user1->id)->update(
                                [
                                    'enabled' => 1,
                                ]
                            );
                            DB::connection('mysql2')->table('users_activecode')->where('member_id', $sub_user1->id)->update(
                                [
                                    'enabled' => 1,
                                ]
                            );

                            $SubResiler2 = SubResiler::where('res_id', $sub_user1->id)->get();
                            if ($SubResiler2) {
                                foreach ($SubResiler2 as $sub2) {
                                    $sub_user2 = User::find($sub2->user_id);
                                    User::whereId($sub_user2->id)->update([
                                        'password' => $sub_user2->blocked,
                                        'blocked' => ''
                                    ]);

                                    ActiveCode::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 1,

                                    ]);
                                    MultiCode::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 1,

                                    ]);
                                    MagDevice::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 1,

                                    ]);
                                    MasterCode::where('user_id', $sub_user2->id)->update([
                                        'enabled' => 1,

                                    ]);
                                    DB::connection('mysql2')->table('users')->where('member_id', $sub_user2->id)->update(
                                        [
                                            'enabled' => 1,
                                        ]
                                    );
                                    DB::connection('mysql2')->table('users_activecode')->where('member_id', $sub_user2->id)->update(
                                        [
                                            'enabled' => 1,
                                        ]
                                    );

                                    $SubResiler3 = SubResiler::where('res_id', $sub_user2->id)->get();
                                    if ($SubResiler3) {
                                        foreach ($SubResiler3 as $sub3) {
                                            $sub_user3 = User::find($sub3->user_id);
                                            User::whereId($sub_user3->id)->update([
                                                'password' => $sub_user3->blocked,
                                                'blocked' => ''
                                            ]);

                                            ActiveCode::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 1,

                                            ]);
                                            MultiCode::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 1,

                                            ]);
                                            MagDevice::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 1,

                                            ]);
                                            MasterCode::where('user_id', $sub_user3->id)->update([
                                                'enabled' => 1,

                                            ]);
                                            DB::connection('mysql2')->table('users')->where('member_id', $sub_user3->id)->update(
                                                [
                                                    'enabled' => 1,
                                                ]
                                            );
                                            DB::connection('mysql2')->table('users_activecode')->where('member_id', $sub_user3->id)->update(
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
        // dd( $request->id );

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        request()->validate([
            'name' => 'required|unique:users',
            'password' => 'required',
            'email' => 'required|unique:users',
            'package_id' => 'required',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = rand(11111111, 99999999) . $file->getClientOriginalName();
            $path = '/home/admin/domains/newpanel.kingiptv.pro/public_html/assets/img';
            $file->move($path, $filename);
            $request->image = $filename;
        } else {
            $filename = 'logo_reseller.png';
        }

        // if ( $user_type == 'Admin' ) {

        //     $type = $request->type;
        // } else {
        //     $type = 'SubResiler';
        // }

        $user = new User();

        $user->name = $request->name;
        $user->password = Hash::make($request->password);
        $user->email = $request->email;
        $user->type = 'SubResiler';
        $user->phone = $request->phone;
        $user->solde = 0;
        $user->image = $filename;
        $user->package_id = $request->package_id;
        $user->host = empty($request->host) ? Auth::user()->host : $request->host;
        $user->show_message = ($request['show_message'] === true || $request['show_message'] === 'true' || $request['show_message'] == 1) ? 1 : 0;

        $user->save();

        $insertedId = $user->id;
        // dd( $insertedId );

        $SubResiler = new SubResiler();

        $SubResiler->user_id = $insertedId;
        $SubResiler->res_id = $user_id;

        $SubResiler->save();

    }

    public function updateProfile(Request $request)
    {
        request()->validate([
            'image' => ['nullable', 'mimes:jpeg,jpg,png,gif'],
        ]);
        $packages = explode(',', $request->packages);
        // dd( $request->Newpassword );
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        // dd( $request->all() );
        $user = User::findOrFail($user_id);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = rand(11111111, 99999999) . $file->getClientOriginalName();
            $path = '/home/admin/domains/newpanel.kingiptv.pro/public_html/assets/img';
            $file->move($path, $filename);
            $request->image = $filename;
        } else {
            $filename = $user->image;
        }

        $current = $user->password;

        if ($request->Newpassword !== null) {

            $pass = Hash::make($request->Newpassword);
        } else {
            $pass = $user->password;
        }

        // dd( $request->password );

        // dd( $pass );
        $i = 1;
        foreach ($packages as $package) {
            $p = Package::where('package_id', $package)->where('user_id', Auth::user()->id)->first();
            if ($p) {
                $p->order = $i;
                $p->save();
            } else {
                Package::create([
                    'user_id' => Auth::user()->id,
                    'package_id' => $package,
                    'order' => $i,
                ]);
            }
            $i++;
        }

        User::whereId(Auth::user()->id)->update([
            'name' => $request['name'],
            'password' => $pass,
            'phone' => $request['phone'] == 'null' ? '' : $request['phone'],
            // 'solde'             => $user->solde,
            'email' => $request['email'],
            'type' => $user->type,
            'host' => $request->host,
            'image' => $filename,
        ]);
    }

    public function GetAllUsers()
    {

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        if ($user_type == 'Admin') {
            $allusers = DB::table('users')
                ->where('users.id', '!=', $user_id)
                ->select('name', 'id')
                ->get();
            return Response()->json($allusers);

        }

    }

    public function selectPack()
    {

        $packs = [];
        $dd = DB::connection('mysql2')->table('packages')->select('packages.*')->get();

        foreach ($dd as $pk) {

            $tt['text'] = $pk->package_name;
            $tt['value'] = $pk->id;

            $packs[] = $tt;
        }

        return Response()->json($packs);
    }

    public function getStatistic($id, $date)
    {
        $today = Carbon::today();
        if ($date == 1) {
            $getResStatistic = DB::table('reseller_statistics')->where('reseller_id', $id)->whereDate('created_at', Carbon::today())->select('operation_name', DB::raw('count(solde) as solde'))->groupBy('operation_name')->get();
        } else {
            if ($date == 2) {
                $getResStatistic = DB::table('reseller_statistics')->where('reseller_id', $id)->whereDate('created_at', '>=', Carbon::yesterday())->select('operation_name', DB::raw('count(solde) as solde'))->groupBy('operation_name')->get();
            } else {
                if ($date == 7) {
                    $getResStatistic = DB::table('reseller_statistics')->where('reseller_id', $id)->whereDate('created_at', '>', Carbon::today()->subDays(7))->select('operation_name', DB::raw('count(solde) as solde'))->groupBy('operation_name')->get();
                }
            }
        }
        $data = [0, 0, 0, 0];

        foreach ($getResStatistic as $value) {
            if ($value->operation_name == 'active_code') {
                $data[0] += $value->solde;
            } else {
                if ($value->operation_name == 'user') {
                    $data[1] = $value->solde;
                } else {
                    if ($value->operation_name == 'mass_code') {
                        $data[0] += $value->solde;
                    } else {
                        if ($value->operation_name == 'mag_device') {
                            $data[2] = $value->solde;
                        } else {
                            if ($value->operation_name == 'add_credit_to_sub_reseller') {
                                $data[3] = $value->solde;
                            }
                        }
                    }
                }
            }
        }

        return response()->json($data);
    }

    public function mySubRes(Request $request)
    {
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        if ($user_type == 'Admin') {
            $subRes = DB::table('users')
                ->select('name', 'id')
                ->where('deleted_at', null)
                ->get();
            foreach ($subRes as $s) {
                $s->user = $s->name;
                $s->user_id = $s->id;
            }
        } else {
            $subRes = SubResiler::where('res_id', $user_id)->get();
            $userIds = $subRes->pluck('user_id')->toArray();
            $users = User::whereIn('id', $userIds)->pluck('name', 'id');

            foreach ($subRes as $s) {
                $s->user = isset($users[$s->user_id]) ? $users[$s->user_id] : '';
            }
        }

        return response()->json($subRes);

    }

    public function app_activation(Request $request)
    {
        try {
            $user_id = auth()->id();
            $user_type = Auth::user()->type;
            $user_solde_app = Auth::user()->solde_app;
            if ($user_type != 'Admin') {
                $user_res = User::whereId($user_id);
                if ($user_solde_app > 0) {
                    $user_res_solde_app = $user_solde_app - $request->solde;

                    User::whereId($user_id)->update([
                        'solde_app' => $user_res_solde_app,
                    ]);
                } else {
                    return response()->json(['error_message' => 'Your balance is insufficient!', 'status' => 500], 500);
                }

            } else {
                return response()->json(['success_message' => 'success', 'status' => 200], 200);
            }

        } catch (\Throwable $th) {
            return response()->json(['error_message' => 'Error !', 'status' => 500], 500);
        }
    }

    public function user_coupons(Request $request)
    {
        $post_data = [
            'page' => $request->page,
            'owner_id' => Auth::user()->id,
        ];
        $headers = [
            'User-Agent' => 'arcapi',
        ];
        $client = new Client();
        $response = $client->post('https://arcplayer.com/api/get_coupons', [
            'json' => $post_data,
            'headers' => $headers
        ]);
        $responseData = json_decode($response->getBody(), true);
        return response()->json($responseData, 200);
    }

    function change_parent(Request $request, $sub_res, $res)
    {

        DB::beginTransaction();
        try {

            $item = SubResiler::where('user_id', $sub_res)->first();
            if ($item) {
                $item->res_id = $res;
                $item->save();
            } else {
                $newSubRes = new SubResiler();
                $newSubRes->user_id = $sub_res;
                $newSubRes->res_id = $res;
                $newSubRes->save();
            }

            DB::commit();
            return response()->json(['success_message' => 'success', 'status' => 200], 200);

        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['error_message' => 'Error !', 'status' => 500], 500);
        }
    }

}
