<?php

namespace App\Http\Controllers;

use App\Channel;
use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;
use File;

class ChannelController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $query = request('query');

        $channels =  DB::connection('mysql2');
        $channels = $channels->table('streams');
        $channels = $channels->join('categories', 'streams.category_id', '=', 'categories.id');
        $channels = $channels->select('streams.*','categories.category_name AS CategoryName');
        if($query) $channels = $channels->where('streams.stream_display_name','LIKE', "%{$query}%");

        $channels = $channels->orderBy('id', 'ASC')->paginate(20);

        return Response()->json($channels);
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
        // dd($request->all());

        
        request()->validate([
            'title' => 'required',
            'category_id' => 'required',
            'type' => 'required',
        ]);


        $now = date("Y-m-d H:i:s");
        $now = strtotime( "$now" );
        $created_at = $now;


        DB::connection('mysql2')->table('streams')->insert(
            [
                
                'type'                        =>   $request->type,
                'category_id'                 =>   $request->category_id ,
                'stream_display_name'         =>    $request->title,
                'stream_source'               =>    $request->url,
                'stream_icon'                 =>    $request->icon,
                'notes'                       =>    '',
                'transcode_attributes'        =>   '',
                'custom_ffmpeg'               =>   '',
                'movie_subtitles'             =>   '',
                'custom_sid'                  =>   '',
                'auto_restart'                =>   '',
                'pids_create_channel'         =>   '',
                'cchannel_rsources'           =>   '',
                'gen_timestamps'              =>    0,
                'added'                       =>   $created_at,

                'number'                      =>    0,
                'probesize_ondemand'          =>   '512000',
                'custom_map'                  =>   '',
                'external_push'               =>   '',
                'delay_minutes'               =>    0,
                's_country'                   =>   'All',
                's_type'                      =>   'All',
                's_tp'                        =>   '',
                's_pid'                       =>   '',
                's_sid'                       =>   '',
                's_pol'                       =>   '',
                's_angele'                    =>   '',


            ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Channel  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function show(Channel $Channel)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Channel  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function edit(Channel $Channel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Channel  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
    //    dd($request->all());


    DB::connection('mysql2')->table('streams')->where('streams.id' , $id)->update(
        [

            'type'                        =>   $request->type,
            'category_id'                 =>   $request->category_id ,
            'stream_display_name'         =>    $request->title,
            'stream_source'               =>    $request->url,
            'stream_icon'                 =>    $request->icon,

        ]);

       
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Channel  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        return DB::connection('mysql2')->table('streams')->where('streams.id' , $id)->delete();

    }
}
