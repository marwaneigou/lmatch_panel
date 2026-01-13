<?php

namespace App\Http\Controllers;

use App\Notification;
use App\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use DB;

class NotificationController extends Controller
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
    public function index()
    {
        $Notification = Notification::all();
        return Response()->json($Notification);



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
        $now = Carbon::now();
        $date = $now->format('Y-m-d');


       $notif=  new Notification();

       $notif->create([
           'notif'  => $request['notif'],
           'date'   =>$date,
       ]);


    }

    /**
     * Display the specified resource.
     *
     * @param  \App\ActiveCode  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function show(Notification $Notification)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Notification  $activeCode
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
     * @param  \App\Notification  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Notification  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $Message = Notification::findOrFail($id);
        $Message->delete();

    }


}
