<?php

namespace App\Http\Controllers;

use App\Category;
use Illuminate\Http\Request;
use Carbon\Carbon;
use File;
use DB;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $query = request('query');

        $cats =  DB::connection('mysql2');
        $cats = $cats->table('categories');
        $cats = $cats->select('categories.*');
        if($query) $cats = $cats->where('categories.category_name','LIKE', "%{$query}%");
        $cats = $cats->paginate(20);

        return Response()->json($cats);
        // dd($cats);

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
            'name' => 'required',
            'type' => 'required',
        ]);


        DB::connection('mysql2')->table('categories')->insert(
            [
                
                'category_type'         =>   $request->type,
                'category_name'         =>   $request->name,
                'category_image'        =>    $request->image,
                'parent_id'             =>    0,
                'cat_order'             =>    0,
                'prid'                  =>    0,
                'type'                  =>   $request->type == 'live'? 1 : '',
                'is_active_or_note'     =>   'ON',
                'categh_id'             =>   0,
                'list_protocols'        =>   $request->type == 'live'?"<br>SMART_IPTV<br>SMART_IPTV_OLD<br>RED_IPTV<br>ATLAS_IPTV<br>AMAZON_IPTV<br>L7_IPTV<br>ROYAL_IPTV<br>ENHANE_IPTV<br>POP_IPTV<br>SAMSAT_IPTV60HD<br>VOLKA_IPTV<br>PINACL_IPTV<br>HOME_IPTV<br>MITV_IPTV<br>MYHD_IPTV<br>VIVA_IPTV<br>OLD_APPS_IPTV<br>NEO_IPTV": '',
                'c_langage'             =>   'All',
                'c_type'                =>   'All',
                'cat_order_country'     =>   'MA',


            ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Category  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function show(Category $Category)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\ActiveCode  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function edit(Category $Category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Category  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        DB::connection('mysql2')->table('categories')->where('categories.id' , $id)->update(
            [
                'category_type'         =>   $request->type,
                'category_name'         =>   $request->name,
                'category_image'        =>    $request->image,

            ]

       );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Category  $activeCode
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
       return DB::connection('mysql2')->table('categories')->where('categories.id' , $id)->delete();

        // $Category->delete();
    }


    public function GetCategories()
    {
        // return Category::orderBy('id', 'ASC')->get();

        return  DB::connection('mysql2')->table('categories')->select('categories.*')->orderBy('id', 'ASC')->get();
    }
}
