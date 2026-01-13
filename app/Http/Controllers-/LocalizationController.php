<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LocalizationController extends Controller
{
    public function index(Request $request,$locale) {
        // app()->setLocale($locale);
        \Session::put('locale', $locale);
        // echo trans('lang.test');
        return redirect()->back();
    }
}
