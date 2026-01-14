<?php

namespace App\Http\Controllers;
use App\User;
use App\ActiveCode;
use App\MasterCode;
use App\MultiCode;
use App\MagDevice;
use App\SubResiler;
use App\Package;

use App\Channel;
use App\Category;
use App\Message;
use App\Parametre;
use DB;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        if (\Request::route()->parameters) {
            if (isset(\Request::route()->parameters['path']) && \Request::route()->parameters['path'] != 'active' && \Request::route()->parameters['path'] != 'active02') {
                $this->middleware('auth');
            }
        }

    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        if (\Request::route()->parameters) {
            if (isset(\Request::route()->parameters['path']) == true && \Request::route()->parameters['path'] == "active") {
                return view('active');
            }

            if (isset(\Request::route()->parameters['path']) == true && \Request::route()->parameters['path'] == "active02") {
                return view('active02');
            }
        }

        $user = Auth::user();
        $is_subreseller = SubResiler::where('user_id', $user->id)->first();
        if ($is_subreseller) {
            $dd = User::find($is_subreseller->res_id);
            if ($dd) {
                $logo = User::find($is_subreseller->res_id)->image;
            } else {
                $logo = $user->image;
            }
        } else {
            $logo = $user->image;
        }
        $token = Auth::user()->session_token;
        $setting = Parametre::first();
        return view('home', compact('logo', 'token', 'setting'));
    }

    public function GetUserAuth()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $user_solde = Auth::user()->solde;
        $name = Auth::user()->name;
        $password = Auth::user()->password;
        $phone = Auth::user()->phone;
        $email = Auth::user()->email;
        $host = Auth::user()->host;
        $user_pack = Auth::user()->package_id;
        $oo = "[$user_pack]";
        $yy = json_decode('[' . $oo . ']', true);

        if ($user_type != 'Admin') {
            $packages = DB::connection('mysql2')->table('packages')->select('packages.*')
                ->whereIn('packages.id', $yy[0])->get();
        } else {
            $packages = DB::connection('mysql2')->table('packages')->select('packages.*')->get();
        }

        // Optimize: Key by package_id for O(1) lookup
        $local_packages = Package::where('user_id', $user_id)->get()->keyBy('package_id');

        // Optimize: Use collection transform instead of nested loops
        $packages->transform(function ($p) use ($local_packages) {
            if ($local_packages->has($p->id)) {
                $lp = $local_packages[$p->id];
                $p->order = $lp->order;
                $p->order_id = $lp->id;
            } else {
                $p->order = '10000';
            }
            return $p;
        });

        // Optimize: Use collection sorting
        $sortedPackages = $packages->sortBy('order')->values();

        return Response()->json([
            'user_id' => $user_id,
            'user_type' => $user_type,
            'user_solde' => $user_solde,
            'name' => $name,
            'password' => $password,
            'phone' => $phone,
            'email' => $email,
            'host' => $host,
            'packages' => $sortedPackages
        ]);
    }


    public function GetCount()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        $change_url = 'hide';

        $settings = Parametre::first();

        if ($user_type != 'Admin') {
            $aa = ActiveCode::where('active_codes.user_id', $user_id)->count();
            $mm = MultiCode::where('multi_codes.user_id', $user_id)->count();
            $MasterCode = MasterCode::where('master_codes.user_id', $user_id)->count();
            $Message = Message::where('messages.recept_id', $user_id)->count();
            $MagDevice = MagDevice::where('mag_devices.user_id', $user_id)->count();
            $users = DB::connection('mysql2')->table('users')->where('member_id', $user_id)->count();



            if ($user_id == 3109) {
                $change_url = 'show';
            } else {
                $subres = SubResiler::where('user_id', $user_id)->first();

                if ($subres) {
                    if ($subres->res_id == 3109) {
                        $change_url = 'show';
                    }
                }
            }

            $ActiveCode = $aa + $mm;
            return Response()->json([
                'ActiveCode' => $ActiveCode,
                'Message' => $Message,
                'Users' => $users,
                'MasterCode' => $MasterCode,
                'MagDevice' => $MagDevice,
                'change_url' => $change_url,
                'settings' => $settings,
            ]);

        } else {
            $aa = ActiveCode::count();
            $mm = MultiCode::count();
            $MasterCode = MasterCode::count();
            $MagDevice = MagDevice::count();
            $Message = Message::where('messages.recept_id', $user_id)->count();
            $Channel = DB::connection('mysql2')->table('streams')->count();
            $Category = DB::connection('mysql2')->table('categories')->count();
            $ActiveCode = $aa + $mm;

            $Resiler = User::where('users.type', '!=', 'Admin')->count();
            $users = DB::connection('mysql2')->table('users')->where('member_id', $user_id)->count();



            return Response()->json([
                'ActiveCode' => $ActiveCode,
                'Channel' => $Channel,
                'Category' => $Category,
                'Message' => $Message,
                'Users' => $users,
                'MasterCode' => $MasterCode,
                'MagDevice' => $MagDevice,
                'Resiler' => $Resiler,
                'change_url' => $change_url,
                'settings' => $settings,
            ]);
        }



    }


    public function GetPack()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        $user_pack = Auth::user()->package_id;
        $oo = "[$user_pack]";
        $yy = json_decode('[' . $oo . ']', true);

        if ($user_type != 'Admin') {
            $packages = DB::connection('mysql2')->table('packages')->select('packages.*')
                ->whereIn('packages.id', $yy[0])->get();
        } else {
            $packages = DB::connection('mysql2')->table('packages')->select('packages.*')->get();
        }

        $local_packages = Package::where('user_id', $user_id)->get()->keyBy('package_id');

        $packages->transform(function ($p) use ($local_packages) {
            if ($local_packages->has($p->id)) {
                $lp = $local_packages[$p->id];
                $p->order = $lp->order;
                $p->order_id = $lp->id;
            } else {
                $p->order = '10000';
            }
            return $p;
        });

        $sortedPackages = $packages->sortBy('order')->values();

        return Response()->json($sortedPackages);
    }

    public function GetPackID($id)
    {
        // $tt = DB::connection('mysql2')->table('packages')->select('packages.*')->where('packages.id' , $id)->first();
        // $names = DB::connection('mysql2')->table('bouquets')->get();
        // $tt->names = $names;
        // $tt->all_bouquets = json_decode($tt->bouquets);
        // $tt->custom_bouquets = json_decode($tt->bouquets);

        // return Response()->json($tt);

        $tt = DB::connection('mysql2')->table('packages')->select('packages.*')->where('packages.id', $id)->first();
        $names = DB::connection('mysql2')->table('bouquets')->get()->toArray();
        $live = [];
        foreach ($names as $bq) {
            if ($bq->bouquet_series == '[]') {
                $bq->type = 'live';
                array_push($live, (int) $bq->id);
            } else {
                $bq->type = 'movie';
            }
        }
        usort($names, function ($a, $b) {
            return $a->type > $b->type ? -1 : 1; //Compare the scores
        });
        $tt->names = $names;
        $tt->all_bouquets = json_decode($tt->bouquets);
        $tt->live = $live;
        $tt->custom_bouquets = json_decode($tt->bouquets);

        return Response()->json($tt);
    }


    public function Cachview()
    {

        \Artisan::call('view:cache');

        return 'cach cleared';
    }



    public function Cachconfig()
    {

        \Artisan::call('config:cache');


        return 'cach cleared';
    }


    public function CachRoute()
    {

        \Artisan::call('route:cache');


        return 'cach cleared';
    }


    public function clearRoute()
    {
        \Artisan::call('route:clear');


        return 'cach cleared';
    }


    public function Clearview()
    {

        \Artisan::call('view:clear');


        return 'cach cleared';
    }


    public function Clearcach()
    {

        \Artisan::call('cache:clear');


        return 'cach cleared';
    }


    public function Clearconfig()
    {
        \Artisan::call('config:clear');

        return 'cach cleared';
    }


    public function optimize()
    {

        \Artisan::call('optimize');

        return 'cach cleared';
    }

    public function getActiveD()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        if ($user_type == 'Admin') {
            $active = ActiveCode::where('enabled', 0)->paginate(10);

            $users = User::get();
            foreach ($active as $act) {
                $act->owner = '';
                foreach ($users as $user) {
                    if ($user->id == $act->user_id) {
                        $act->owner = $user->name;
                    }
                }
            }
            return response()->json(['active' => $active]);
        } else {
            abort(404);
        }
    }
    public function getUsersD()
    {
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        if ($user_type == 'Admin') {
            $users = DB::connection('mysql2')->table('users')->where('users.enabled', 0)->paginate(10);

            $users_l = User::get();
            foreach ($users as $act) {
                $act->owner = '';
                foreach ($users_l as $user) {
                    if ($act->owner_name != '') {
                        $act->owner = $act->owner_name;
                    } else {
                        if ($user->id == $act->member_id) {
                            $act->owner = $user->name;
                        }
                    }

                }
            }
            return response()->json(['users' => $users]);
        } else {
            abort(404);
        }
    }

    public function enableUsersD($number)
    {
        $get_user = DB::connection('mysql2')->table('users')->where('username', $number)->first();
        if ($get_user) {
            return DB::connection('mysql2')->table('users')->where('username', $number)->update(
                [
                    'enabled' => 1,
                ]
            );
        } else {
            return DB::connection('mysql2')->table('users_activecode')->where('username', $number)->update(
                [
                    'enabled' => 1,
                ]
            );
        }
    }

    public function enableActiveD($number)
    {
        ActiveCode::where('number', $number)->update([
            'enabled' => 1,

        ]);
        $get_user = DB::connection('mysql2')->table('users')->where('username', $number)->first();
        if ($get_user) {
            return DB::connection('mysql2')->table('users')->where('username', $number)->update(
                [
                    'enabled' => 1,
                ]
            );
        } else {
            return DB::connection('mysql2')->table('users_activecode')->where('username', $number)->update(
                [
                    'enabled' => 1,
                ]
            );
        }
    }


    // function check_duplicated(Request $request) {
    //     // $users = User::pluck('id');
    //     // $xt_users = DB::connection('mysql2')->table('members')->whereIn('id', $users)->orderBy("id")->select("id", "username")->get();

    //     $xt_users = DB::connection('mysql2')->table('members')->pluck('id');
    //     $users = User::whereIn('id', $xt_users)->select("id", "name")->orderBy("id")->get();

    //     return response()->json($users);

    // }
}
