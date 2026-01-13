<?php

namespace App\Http\Controllers;

use App\Message;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

use DB;
use File;


class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // $messages = Message::all();
        // return Response()->json($messages);

        // return Message::latest()->paginate(10);

        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;
        
        // return ActiveCode::find($user_id)->latest()->paginate(10);
        if($user_type !== 'Admin'){
            $messages =  DB::table('messages')
            ->join('users', 'messages.user_id', '=', 'users.id')
            ->select('messages.*','users.name')
            ->where('messages.recept_id', '=', $user_id)
            ->where('messages.deleted_at','=', Null)
            ->orderBy('id', 'ASC')->paginate(10);

            // dd($users);
            return Response()->json($messages);

        }else {
            $messages =  DB::table('messages')
            ->join('users', 'messages.user_id', '=', 'users.id')
            ->where('messages.recept_id', '=', $user_id)
            ->where('messages.deleted_at','=', Null)
            ->select('messages.*','users.name' )
            ->orderBy('id', 'ASC')->paginate(10);

            return Response()->json($messages);
        }


        dd($messages);
  
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
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;

        Message::create([
            'objet'              => $request['objet'],
            'content'            => $request['content'],
            'user_id'           => $user_id,
            'recept_id'         => '3002'
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
        // dd($request->all());
       
        $user = Auth::user();
        $user_id = auth()->id();
        $user_type = Auth::user()->type;


        Message::whereId($id)->update([
            'objet'             => $request['objet'],
            'content'           => $request['content'],
            'user_id'           => $user_id,
            'recept_id'         => $request['recept_id'],
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
        $Message = Message::findOrFail($id);
        $Message->delete();
    }


}
