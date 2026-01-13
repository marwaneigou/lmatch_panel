<?php

//Clear route cache:
Route::get('/route-cache', function () {
    $exitCode = Artisan::call('route:cache');
    return 'Routes cache cleared';
});

//Clear config cache:
Route::get('/config-cache', function () {
    $exitCode = Artisan::call('config:cache');
    return 'Config cache cleared';
});

// Clear application cache:
Route::get('/clear-cache', function () {
    $exitCode = Artisan::call('cache:clear');
    return 'Application cache cleared';
});

// Clear view cache:
Route::get('/view-clear', function () {
    $exitCode = Artisan::call('view:clear');
    return 'View cache cleared';
});

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Auth::routes(['register' => false]);
// Auth::routes();

Route::get('/', 'HomeController@index')->middleware('auth');



Route::post('/code/getUsersD', 'HomeController@getUsersD')->middleware('auth');
Route::get('/code/getUsersD', function () {
    abort(404);
})->middleware('auth');
Route::post('/code/getActiveD', 'HomeController@getActiveD')->middleware('auth');
Route::get('/code/getActiveD', function () {
    abort(404);
})->middleware('auth');
Route::post('/code/UsersD/{number}', 'HomeController@enableUsersD')->middleware('auth');
Route::post('/code/ActiveD/{number}', 'HomeController@enableActiveD')->middleware('auth');

Route::post('/code/EnableMC/{number}', 'MultiCodeController@enableMassCodeD')->middleware('auth');
Route::post('changeDays/mulitcode/{id}', 'MultiCodeController@changeDays')->middleware('auth');

Route::post('/code/EnableMD/{id}', 'MagDeviceController@enableMagD')->middleware('auth');



Route::get('{path}', 'HomeController@index')->where('path', '([A-z/]+)?');
// Route::get('{path}','HomeController@index')->where( 'path', '^(?!.*(/active)).*$' );


//************************** Transaction ***********************/

Route::resource('code/transactions', 'ResellerStatisticController', ['only' => ['update', 'store', 'destroy']])->middleware('auth');
Route::post('code/transactions_all', 'ResellerStatisticController@index')->middleware('auth');
Route::get('/code/transactions_all', function () {
    abort(404);
})->middleware('auth');
Route::post('code/transactions_by_res', 'ResellerStatisticController@transactions_by_res')->middleware('auth');
Route::get('/code/transactions_by_res', function () {
    abort(404);
})->middleware('auth');

//--------------------Active Code --------------------------------------\\

Route::resource('code/acivecodes', 'ActiveCodeController', ['only' => ['update', 'store', 'destroy']])->middleware('auth');
Route::post('code/acivecodes_all', 'ActiveCodeController@index')->middleware('auth');
Route::get('/code/acivecodes_all', function () {
    abort(404);
})->middleware('auth');
Route::delete('code/acivecodes/{id}/{type}', 'ActiveCodeController@destroy')->middleware(['auth', 'log.route']);



//--------------------Master Code --------------------------------------\\
Route::resource('code/mastercodes', 'MasterCodeController', ['only' => ['update', 'store', 'destroy']])->middleware('auth');
Route::post('code/mastercodes_all', 'MasterCodeController@index')->middleware('auth');
Route::get('/code/mastercodes_all', function () {
    abort(404);
})->middleware('auth');
Route::delete('/code/mastercodesDelete/{number}/{mac}/{type}', 'MasterCodeController@deleteMastercode')->middleware(['auth', 'log.route']);
Route::post('/code/enableMastercode/{number}/{mac}', 'MasterCodeController@enableMastercode')->middleware('auth');
Route::post('changeDays/mastercode/{mac}', 'MasterCodeController@changeDays')->middleware('auth');
//--------------------Multi Code --------------------------------------\\
Route::resource('code/multicodes', 'MultiCodeController', ['only' => ['update', 'store', 'destroy']])->middleware('auth');
Route::post('code/multicodes_all', 'MultiCodeController@index')->middleware('auth');
Route::get('/code/multicodes_all', function () {
    abort(404);
})->middleware('auth');
Route::delete('code/multicodes/{id}/{type}', 'MultiCodeController@destroy')->middleware(['auth', 'log.route']);

//--------------------Mag Device --------------------------------------\\

Route::resource('mag/Devices', 'MagDeviceController', ['only' => ['update', 'store', 'destroy']])->middleware('auth');
Route::post('mag/Devices_all', 'MagDeviceController@index')->middleware('auth');
Route::get('/mag/Devices_all', function () {
    abort(404);
})->middleware('auth');
Route::delete('mag/Devices/{id}/{type}', 'MagDeviceController@destroy')->middleware(['auth', 'log.route']);
Route::post('changeDays/magdevice/{id}', 'MagDeviceController@changeDays')->middleware('auth');

//------------------------Category --------------------------------------\\

Route::resource('code/categories', 'CategoryController', ['only' => ['update', 'store', 'destroy']])->middleware(['auth', 'admin']);
Route::post('code/categories_all', 'CategoryController@index')->middleware('auth');
Route::get('/code/categories_all', function () {
    abort(404);
})->middleware('auth');
Route::post('code/Getcategories', 'CategoryController@GetCategories')->middleware(['auth', 'admin']);
Route::get('code/Getcategories', function () {
    abort(404);
})->middleware('auth');
//------------------------Channel --------------------------------------\\
Route::resource('code/channels', 'ChannelController', ['only' => ['update', 'store', 'destroy']])->middleware(['auth', 'admin']);
Route::post('code/channels_all', 'ChannelController@index')->middleware('auth');
Route::get('/code/channels_all', function () {
    abort(404);
})->middleware('auth');
//------------------------Resiler --------------------------------------\\
Route::resource('code/resilers', 'ResilerController', ['only' => ['update', 'store', 'destroy']])->middleware('auth');
Route::post('code/resilers_all', 'ResilerController@index')->middleware('auth');
Route::get('/code/resilers_all', function () {
    abort(404);
})->middleware('auth');
Route::put('code/updateCredit/{id}', 'ResilerController@updateCredit')->middleware(['auth', 'log.route']);
Route::put('code/recoverCredit/{id}', 'ResilerController@recoverCredit')->middleware(['auth', 'log.route']);
Route::post('code/block/{id}', 'ResilerController@block')->middleware('auth');
Route::post('code/unblock/{id}', 'ResilerController@unblock')->middleware('auth');

Route::post('code/GetUserAuth', 'HomeController@GetUserAuth')->middleware('auth');
Route::get('/code/GetUserAuth', function () {
    abort(404);
})->middleware('auth');
Route::post('code/CreateSubResiler', 'ResilerController@CreateSubResiler')->middleware('auth');

Route::put('code/updateProfile', 'ResilerController@updateProfile')->middleware(['auth', 'log.route']);

Route::post('code/GetAllUsers', 'ResilerController@GetAllUsers')->middleware('auth');
Route::get('/code/GetAllUsers', function () {
    abort(404);
})->middleware('auth');

Route::post('code/getStatistic/{id}/{date}', 'ResilerController@getStatistic')->middleware('auth');
Route::get('code/getStatistic/{id}/{date}', function () {
    abort(404);
})->middleware('auth');

Route::post('code/change_parent/{sub_res}/{res}', 'ResilerController@change_parent')->middleware(['auth', 'admin']);

//------------------------Notification --------------------------------------\\

Route::resource('code/notifications', 'NotificationController', ['only' => ['update', 'store', 'destroy']])->middleware('auth');
Route::post('code/notifications_all', 'NotificationController@index')->middleware('auth');
Route::get('/code/notifications_all', function () {
    abort(404);
})->middleware('auth');

//------------------------Message --------------------------------------\\

Route::resource('code/messages', 'MessageController')->middleware('auth');

//------------------------Count --------------------------------------\\

Route::post('code/GetCount', 'HomeController@GetCount')->middleware('auth');
Route::get('/code/GetCount', function () {
    abort(404);
})->middleware('auth');
//------------------------package --------------------------------------\\

Route::post('code/GetPack', 'HomeController@GetPack')->middleware('auth');
Route::get('/code/GetPack', function () {
    abort(404);
})->middleware('auth');
Route::post('code/GetPackID/{id}', 'HomeController@GetPackID')->middleware('auth');
Route::get('/code/GetPackID/{id}', function () {
    abort(404);
})->middleware('auth');





//------------------------Activate --------------------------------------\\

Route::get('protocol/Activate.php', 'ProtocoleController@activate');
Route::get('protocol/Activate1.php', 'ProtocoleController@activate1');
Route::get('protocol/Run.php', 'ProtocoleController@Run');
Route::get('protocol/Channel.php', 'ProtocoleController@channel');
Route::get('protocol/Notifications.php', 'ProtocoleController@notifications');
Route::get('protocol/Code.php', 'ProtocoleController@getPassword');




// Route::get('/code/test', function () {
//     return view('welcome');
// });




// // //Clear Cache facade value:
// Route::get('/clear-cache', function() {
//     $exitCode = Artisan::call('cache:clear');
//     return '<h1>Cache facade value cleared</h1>';
// });

// //Reoptimized class loader:
// Route::get('/optimize-clear', function() {
//     $exitCode = Artisan::call('optimize');
//     return '<h1>Reoptimized class loader</h1>';
// });

// // //Route cache:
// Route::get('/route-cache', function() {
//     $exitCode = Artisan::call('route:cache');
//     return '<h1>Routes cached</h1>';
// });

// //Clear Route cache:
// Route::get('/route-clear', function() {
//     $exitCode = Artisan::call('route:clear');
//     return '<h1>Route cache cleared</h1>';
// });

// //Clear View cache:
// Route::get('/view-clear', function() {
//     $exitCode = Artisan::call('view:clear');
//     return '<h1>View cache cleared</h1>';
// });

// //Clear Config cache:
// Route::get('/config-cache', function() {
//     $exitCode = Artisan::call('config:cache');
//     return '<h1>Clear Config cleared</h1>';
// });



Route::get('/cach/route', 'HomeController@CachRoute');

Route::get('/cach/config', 'HomeController@Cachconfig');

Route::get('/cach/view', 'HomeController@Cachview');


Route::get('/clear/view', 'HomeController@Clearview');

Route::get('/clear/cach', 'HomeController@Clearcach');

Route::get('/clear/config', 'HomeController@Clearconfig');

Route::get('/clear/route', 'HomeController@clearRoute');

Route::get('/optimize-clear', 'HomeController@optimize');


Route::post('code/check', 'MultiCodeController@check')->middleware('auth');
Route::post('code/getSolde', 'MultiCodeController@getSolde')->middleware('auth');
Route::get('/code/getSolde', function () {
    abort(404);
})->middleware('auth');


//function Reset Mac Adress

Route::put('ActiveCode/resetMac/{id}', 'ActiveCodeController@resetMac')->middleware(['auth', 'log.route']);


Route::post('changeDays/active/{id}', 'ActiveCodeController@changeDays')->middleware('auth');

Route::put('Mastercode/resetMac/{id}', 'MasterCodeController@resetMac')->middleware(['auth', 'log.route']);


Route::put('Masscode/resetMac/{id}', 'MultiCodeController@resetMac')->middleware(['auth', 'log.route']);

Route::put('mag/Devices/resetMac/{id}', 'MagDeviceController@resetMac')->middleware(['auth', 'log.route']);



Route::post('code/getrequest', 'MasterCodeController@getRequest')->middleware('auth');

Route::post('code/SendMassCode', 'MultiCodeController@SendMassCode')->middleware('auth');

Route::post('code/showM3U', 'ActiveCodeController@showM3U')->name('showM3U');

Route::post('mag/Devices/showM3U', 'MagDeviceController@showM3U')->middleware('auth');




Route::put('code/renew/{code}', 'ActiveCodeController@Renew')->middleware(['auth', 'log.route']);

Route::put('codemaster/renew/{code}', 'MasterCodeController@Renew')->middleware(['auth', 'log.route']);

Route::put('codemass/renew/{code}', 'MultiCodeController@Renew')->middleware(['auth', 'log.route']);

Route::put('mag/Devices/renew/{mac}', 'MagDeviceController@Renew')->middleware(['auth', 'log.route']);


Route::get('mag/getmag', 'MagDeviceController@getmag')->middleware('auth');


Route::post('code/pdf/{codes}/{duree}/{coupons}', 'MultiCodeController@pdf')->middleware('auth');
// Route::get('code/pdf/{param}', 'MultiCodeController@pdf')->middleware('auth')->where('param', '[A-Za-z]+');;

// Route::get('code/selectPack','ResilerController@selectPack')->middleware('auth');





// User

Route::post('code/users_list', 'UtilisateurController@index')->middleware('auth');
Route::get('/code/users_list', function () {
    abort(404);
})->middleware('auth');
Route::post('code/add_user', 'UtilisateurController@store')->middleware('auth');
Route::put('code/update_user/{id}', 'UtilisateurController@update')->middleware(['auth', 'log.route']);
Route::post('code/userShowM3U', 'UtilisateurController@showM3U')->middleware('auth');
Route::put('code/resetUserMac/{id}', 'UtilisateurController@resetMac')->middleware(['auth', 'log.route']);
Route::put('code/renewUser/{code}', 'UtilisateurController@Renew')->middleware(['auth', 'log.route']);
Route::delete('code/delete_user/{id}/{type}', 'UtilisateurController@destroy')->middleware(['auth', 'log.route']);
Route::post('changeDays/users/{id}', 'UtilisateurController@changeDays')->middleware('auth');
Route::post('code/checkSolde', 'UtilisateurController@checkSolde')->middleware('auth');
Route::get('code/checkSolde', function () {
    abort(404);
})->middleware('auth');

//VPN Routes
Route::post('code/vpn/activate', 'VpnController@activateVpn')->middleware('auth');
Route::get('code/vpn/settings', 'VpnController@getVpnSettings')->middleware('auth');
Route::post('code/vpn/settings', 'VpnController@updateVpnSettings')->middleware('auth');
Route::get('code/vpn/download/{userId}', 'VpnController@generateVpnDownload')->middleware('auth');
Route::get('code/vpn/status/{userId}', 'VpnController@checkVpnStatus')->middleware('auth');


//iActive
Route::get('/active', 'iActiveController@index')->name('active');
Route::get('/active02', 'iActiveController@index02')->name('active02');
Route::post('active/acivecodes', 'iActiveController@acivecodes');
Route::get('active/acivecodes', function () {
    abort(404);
})->middleware('auth');
Route::post('active/activate', 'iActiveController@activate');


//Data by resseler
Route::post('code/all_resellers', 'ResilerController@mySubRes')->middleware('auth');
Route::post('code/ac_byres/{resID}', 'ActiveCodeController@byReseller')->middleware('auth');
Route::post('code/user_byres/{resID}', 'UtilisateurController@byReseller')->middleware('auth');
Route::post('code/mass_byres/{resID}', 'MultiCodeController@byReseller')->middleware('auth');
Route::post('code/mag_byres/{resID}', 'MagDeviceController@byReseller')->middleware('auth');

//Parametres
Route::post('/settings', 'ParametreController@index')->middleware('auth');
Route::post('/settings/update', 'ParametreController@update')->middleware('auth');
Route::post('/settings/ads', 'ParametreController@setAds')->middleware('auth');
Route::post('/settings/removeAds', 'ParametreController@removeAds')->middleware('auth');


//ARCPlayer api
Route::post('code/app_activation', 'ResilerController@app_activation')->name('arcplayer');

//ActiveCode filter
Route::post('code/activecode/expired', 'ActiveCodeController@expiredItems')->middleware('auth');
Route::post('code/activecode/expired/{resID}', 'ActiveCodeController@expiredItemsByUser')->middleware('auth');

Route::post('code/activecode/online', 'ActiveCodeController@onlineItems')->middleware('auth');
Route::post('code/activecode/online/{resID}', 'ActiveCodeController@onlineItemsByUser')->middleware('auth');

Route::post('code/activecode/almost_expired', 'ActiveCodeController@amolstExpiredItems')->middleware('auth');
Route::post('code/activecode/almost_expired/{resID}', 'ActiveCodeController@amolstExpiredItemsByUser')->middleware('auth');

Route::post('code/activecode/is_trial', 'ActiveCodeController@trialItems')->middleware('auth');
Route::post('code/activecode/is_trial/{resID}', 'ActiveCodeController@trialItemsByUser')->middleware('auth');

Route::post('code/activecode/disabled', 'ActiveCodeController@disabledItems')->middleware('auth');
Route::post('code/activecode/disabled/{resID}', 'ActiveCodeController@disabledItemsByUser')->middleware('auth');

Route::post('code/activecode/enabled', 'ActiveCodeController@enabledItems')->middleware('auth');
Route::post('code/activecode/enabled/{resID}', 'ActiveCodeController@enabledItemsByUser')->middleware('auth');


//Users filter
Route::post('code/users/expired', 'UtilisateurController@expiredItems')->middleware('auth');
Route::post('code/users/expired/{resID}', 'UtilisateurController@expiredItemsByUser')->middleware('auth');

Route::post('code/users/online', 'UtilisateurController@onlineItems')->middleware('auth');
Route::post('code/users/online/{resID}', 'UtilisateurController@onlineItemsByUser')->middleware('auth');

Route::post('code/users/almost_expired', 'UtilisateurController@amolstExpiredItems')->middleware('auth');
Route::post('code/users/almost_expired/{resID}', 'UtilisateurController@amolstExpiredItemsByUser')->middleware('auth');

Route::post('code/users/is_trial', 'UtilisateurController@trialItems')->middleware('auth');
Route::post('code/users/is_trial/{resID}', 'UtilisateurController@trialItemsByUser')->middleware('auth');

Route::post('code/users/disabled', 'UtilisateurController@disabledItems')->middleware('auth');
Route::post('code/users/disabled/{resID}', 'UtilisateurController@disabledItemsByUser')->middleware('auth');

Route::post('code/users/enabled', 'UtilisateurController@enabledItems')->middleware('auth');
Route::post('code/users/enabled/{resID}', 'UtilisateurController@enabledItemsByUser')->middleware('auth');

//Magdevice filter
Route::post('code/magdevices/expired', 'MagDeviceController@expiredItems')->middleware('auth');
Route::post('code/magdevices/expired/{resID}', 'MagDeviceController@expiredItemsByUser')->middleware('auth');

Route::post('code/magdevices/online', 'MagDeviceController@onlineItems')->middleware('auth');
Route::post('code/magdevices/online/{resID}', 'MagDeviceController@onlineItemsByUser')->middleware('auth');

Route::post('code/magdevices/almost_expired', 'MagDeviceController@amolstExpiredItems')->middleware('auth');
Route::post('code/magdevices/almost_expired/{resID}', 'MagDeviceController@amolstExpiredItemsByUser')->middleware('auth');

Route::post('code/magdevices/is_trial', 'MagDeviceController@trialItems')->middleware('auth');
Route::post('code/magdevices/is_trial/{resID}', 'MagDeviceController@trialItemsByUser')->middleware('auth');

Route::post('code/magdevices/disabled', 'MagDeviceController@disabledItems')->middleware('auth');
Route::post('code/magdevices/disabled/{resID}', 'MagDeviceController@disabledItemsByUser')->middleware('auth');

Route::post('code/magdevices/enabled', 'MagDeviceController@enabledItems')->middleware('auth');
Route::post('code/magdevices/enabled/{resID}', 'MagDeviceController@enabledItemsByUser')->middleware('auth');

//MassCode filter
Route::post('code/masscode/expired', 'MultiCodeController@expiredItems')->middleware('auth');
Route::post('code/masscode/expired/{resID}', 'MultiCodeController@expiredItemsByUser')->middleware('auth');

Route::post('code/masscode/online', 'MultiCodeController@onlineItems')->middleware('auth');
Route::post('code/masscode/online/{resID}', 'MultiCodeController@onlineItemsByUser')->middleware('auth');

Route::post('code/masscode/almost_expired', 'MultiCodeController@amolstExpiredItems')->middleware('auth');
Route::post('code/masscode/almost_expired/{resID}', 'MultiCodeController@amolstExpiredItemsByUser')->middleware('auth');

Route::post('code/masscode/is_trial', 'MultiCodeController@trialItems')->middleware('auth');
Route::post('code/masscode/is_trial/{resID}', 'MultiCodeController@trialItemsByUser')->middleware('auth');

Route::post('code/masscode/disabled', 'MultiCodeController@disabledItems')->middleware('auth');
Route::post('code/masscode/disabled/{resID}', 'MultiCodeController@disabledItemsByUser')->middleware('auth');

Route::post('code/masscode/enabled', 'MultiCodeController@enabledItems')->middleware('auth');
Route::post('code/masscode/enabled/{resID}', 'MultiCodeController@enabledItemsByUser')->middleware('auth');


//Domains
Route::post('code/add_host', 'DomainController@store')->middleware('auth');
Route::post('code/update_host/{id}', 'DomainController@update')->middleware('auth');
Route::post('code/delete_host/{id}', 'DomainController@destroy')->middleware('auth');
Route::post('code/hosts', 'DomainController@index')->middleware('auth');

//Localization
Route::post('/code/set_localization/{locale}', 'LocalizationController@index');

//Coupons
Route::post('code/coupons', 'ResilerController@user_coupons')->middleware('auth');

// Route::post('/locale/{locale}', function ($locale){
//     // Session::put('locale', $locale);
//     // session()->put('locale', $locale);
//     app()->setLocale($locale);
//     return redirect()->back();
// });

// Route::post('/locale2/{locale}', function ($locale){
//     Session::put('locale', $locale);
//     return redirect()->back();
// });