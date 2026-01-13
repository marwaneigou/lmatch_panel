<?php

namespace App\Http\Controllers;

use App\Domain;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DomainController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $domains = Domain::paginate(20);
        return response()->json($domains);
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
        request()->validate([
            'host' => 'required|unique:domains',
            'background' => 'required|mimes:jpeg,jpg,png,gif',
            'logo' => 'required|mimes:jpeg,jpg,png,gif'
        ]);

        if ( ($request['host'] != "null" && $request['host'] != null && $request['host'] != '')  && !preg_match("/^([a-z0-9-]+\.){1,}+[a-z0-9]+$/i",$request['host'])) {
            return response(['message'=>'The given data was invalid.', "errors"=>['host'=>['Invalid URl']]], 422);
        }

        if($request->hasFile('logo')){
            $file = $request->file('logo');
            $filename = rand(11111111, 99999999) .$file->getClientOriginalName();
            $path = '/home/admin/domains/newpanel.kingiptv.pro/public_html/assets/img';
            // $path = public_path()."/assets/img";
            $file->move($path, $filename);
            $request->logo=$filename;
        }else {
            abort(401);
        }

        if($request->hasFile('background')){
            $file = $request->file('background');
            $filename = rand(11111111, 99999999) .$file->getClientOriginalName();
            $path = '/home/admin/domains/newpanel.kingiptv.pro/public_html/assets/img';
            // $path = public_path()."/assets/img";
            $file->move($path, $filename);
            $request->background=$filename;
        }else {
            abort(401);
        }

        Domain::create([
            'host' => $request->host,
            'background' => $request->background,
            'logo' => $request->logo
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Domain  $domain
     * @return \Illuminate\Http\Response
     */
    public function show(Domain $domain)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Domain  $domain
     * @return \Illuminate\Http\Response
     */
    public function edit(Domain $domain)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Domain  $domain
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        request()->validate([
            'host' => ['required', Rule::unique( 'domains' )->ignore( $id )],
            'background' => 'mimes:jpeg,jpg,png,gif',
            'logo' => 'mimes:jpeg,jpg,png,gif'
        ]);

        if ( ($request['host'] != "null" && $request['host'] != null && $request['host'] != '')  && !preg_match("/^([a-z0-9-]+\.){1,}+[a-z0-9]+$/i",$request['host'])) {
            return response(['message'=>'The given data was invalid.', "errors"=>['host'=>['Invalid URl']]], 422);
        }

        $domain = Domain::find($id);
        if($domain) {
            if($request->hasFile('logo')){
                $file = $request->file('logo');
                $filename = rand(11111111, 99999999) .$file->getClientOriginalName();
                $path = '/home/admin/domains/newpanel.kingiptv.pro/public_html/assets/img';
                // $path = public_path()."/assets/img";
                $file->move($path, $filename);
                $request->logo=$filename;
            }else {
                $request->logo = $domain->logo;
            }
    
            if($request->hasFile('background')){
                $file = $request->file('background');
                $filename = rand(11111111, 99999999) .$file->getClientOriginalName();
                $path = '/home/admin/domains/newpanel.kingiptv.pro/public_html/assets/img';
                // $path = public_path()."/assets/img";
                $file->move($path, $filename);
                $request->background=$filename;
            }else {
                $request->background = $domain->background;
            }
    
            Domain::whereId($id)->update([
                'host' => $request->host,
                'background' => $request->background,
                'logo' => $request->logo
            ]);
        }else{
            abort(401);
        }        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Domain  $domain
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $domain = Domain::find($id);
        if($domain) {
            $domain->delete();
        }else{
            abort(401);
        }
    }
}
