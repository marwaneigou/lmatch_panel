<?php

namespace App\Http\Controllers;

use App\Parametre;
use Illuminate\Http\Request;

use Auth;

class ParametreController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        if(Auth::user()->type == 'Admin') {
            $setting = Parametre::take(1)->get();
            return response()->json($setting, 200);
        }else{
            abort(401);
        }        
    }

    // public function checkTest(Request $req)
    // {
    //     if(Auth::user()->type == 'Admin') {
    //         Parametre::whereId(1)->update([
    //             'test_active' => $req->testactive,
    //             'bande' => $req->notification
    //         ]);
    //         return response()->json(['success'=> 'success'], 200);
    //     }else{
    //         abort(401);
    //     }        
    // }

    // public function chekc(Request $req)
    // {
    //     if(Auth::user()->type == 'Admin') {
    //         Parametre::whereId(1)->update([
    //             'bande' => $req->notification
    //         ]);
    //         return response()->json(['success'=> 'success'], 200);
    //     }else{
    //         abort(401);
    //     }        
    // }

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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Parametre  $parametre
     * @return \Illuminate\Http\Response
     */
    public function show(Parametre $parametre)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Parametre  $parametre
     * @return \Illuminate\Http\Response
     */
    public function edit(Parametre $parametre)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Parametre  $parametre
     * @return \Illuminate\Http\Response
     */
    public function update(Request $req)
    {
        if(Auth::user()->type == 'Admin') {
            Parametre::whereId(1)->update([
                'test_active' => ($req->testactive == true || $req->testactive == 1) ? 1 : 0,
                'bande' => $req->notification
            ]);
            return response()->json(['success'=> 'success'], 200);
        }else{
            abort(401);
        }   
    }

  
    public function setAds(Request $request) {
        request()->validate([
            'image' => 'required|mimes:jpeg,jpg,png,gif',
        ]);
        
        if(Auth::user()->type == 'Admin') {

            if($request->hasFile('image')){
                $file = $request->file('image');
                    $filename = rand(11111111, 99999999) .$file->getClientOriginalName();
                    $path = '/var/www/vhosts/newpanel.kingiptv.pro/httpdocs/assets/img';
                    // $path = public_path()."/assets/img";
                    $file->move($path, $filename);
                    $request->image=$filename;
            }else{
                return response()->json(['success'=> 'Error'], 500);
            }
            
            Parametre::whereId(1)->update([
                'ads' => $request->image
            ]);
            return response()->json(['success'=> 'success'], 200);
        }else{
            abort(401);
        }   
    }

    public function removeAds(Request $req)
    {
        if(Auth::user()->type == 'Admin') {
            Parametre::whereId(1)->update([
                'ads' => ''
            ]);
            return response()->json(['success'=> 'success'], 200);
        }else{
            abort(401);
        }   
    }
}
