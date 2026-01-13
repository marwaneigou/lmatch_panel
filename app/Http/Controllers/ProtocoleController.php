<?php

namespace App\Http\Controllers;
use App\User;
use App\ActiveCode;
use App\MultiCode;
use App\MasterCode;

use App\Channel;
use App\Category;
use App\Message;
use DB;
use Carbon\Carbon;
use App\Notification;



use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProtocoleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */



    public function activate()
    {
        // Input validation and sanitization
        $code = $_GET["a"] ?? null;
        $mac = strtolower($_GET["m"] ?? '');

        // Block 720 codes
        if ($code && substr($code, 0, 3) === '720') {
            return $this->errorResponse('Invalid Account');
        }

        if (!$code || !$mac) {
            return $this->errorResponse('Invalid parameters');
        }

        // Find the code in different tables (simplified lookup)
        $codeRecord = $this->findCodeRecord($code, $mac);
        if (!$codeRecord) {
            return $this->errorResponse('Invalid Account');
        }

        // Check for MAC reset scenario
        $this->handleMacReset($code, $mac, $codeRecord);

        // Get user activation details
        $userActivationDetails = $this->getUserActivationDetails($code, $mac);
        if (!$userActivationDetails) {
            return $this->errorResponse('Invalid Account');
        }

        // Check if account is expired
        if ($this->isAccountExpired($code)) {
            return $this->errorResponse('Expired account');
        }

        // Process activation based on type
        $activationResult = $this->processActivation($code, $mac, $userActivationDetails, $codeRecord);

        if ($activationResult['status'] !== '100') {
            return Response()->json($activationResult);
        }

        // Get additional data if type parameter is set
        $additionalData = isset($_GET['type']) ? $this->getChannelData($code, $mac) : [];

        return Response()->json(array_merge($activationResult, ['data' => $additionalData]));
    }

    public function activate1()
    {
        // Input validation and sanitization
        $code = $_GET["a"] ?? null;
        $mac = strtolower($_GET["m"] ?? '');

        // Only allow 720 codes
        if (!$code || substr($code, 0, 3) !== '720') {
            return $this->errorResponse('Invalid Account');
        }

        if (!$code || !$mac) {
            return $this->errorResponse('Invalid parameters');
        }

        // Find the code in different tables (simplified lookup)
        $codeRecord = $this->findCodeRecord($code, $mac);
        if (!$codeRecord) {
            return $this->errorResponse('Invalid Account');
        }

        // Check for MAC reset scenario
        $this->handleMacReset($code, $mac, $codeRecord);

        // Get user activation details
        $userActivationDetails = $this->getUserActivationDetails($code, $mac);
        if (!$userActivationDetails) {
            return $this->errorResponse('Invalid Account');
        }

        // Check if account is expired
        if ($this->isAccountExpired($code)) {
            return $this->errorResponse('Expired account');
        }

        // Process activation based on type
        $activationResult = $this->processActivation($code, $mac, $userActivationDetails, $codeRecord);

        if ($activationResult['status'] !== '100') {
            return Response()->json($activationResult);
        }

        // Get additional data if type parameter is set
        $additionalData = isset($_GET['type']) ? $this->getChannelData($code, $mac) : [];

        return Response()->json(array_merge($activationResult, ['data' => $additionalData]));
    }

    private function findCodeRecord($code, $mac)
    {
        // Try ActiveCode first
        $record = ActiveCode::where('number', $code)->first();
        if ($record)
            return $record;

        // Try MasterCode with MAC
        $record = MasterCode::where('number', $code)->where('mac', $mac)->first();
        if ($record)
            return $record;

        // Try MultiCode
        return MultiCode::where('number', $code)->first();
    }

    private function handleMacReset($code, $mac, $codeRecord)
    {
        $macResetUser = DB::connection('mysql2')
            ->table('users')
            ->where('username', $code)
            ->where('macadress', 'Mac Reseted')
            ->first();

        if (!$macResetUser || !$codeRecord) {
            return;
        }

        // Update the appropriate code table
        if ($codeRecord instanceof ActiveCode) {
            ActiveCode::where('id', $codeRecord->id)->update(['mac' => $mac]);
        } elseif ($codeRecord instanceof MasterCode) {
            MasterCode::where('id', $codeRecord->id)->update(['mac' => $mac]);
        } elseif ($codeRecord instanceof MultiCode) {
            MultiCode::where('id', $codeRecord->id)->update(['mac' => $mac]);
        }

        // Update user MAC address
        DB::connection('mysql2')
            ->table('users')
            ->where('username', $code)
            ->update(['macadress' => $mac]);
    }

    private function getUserActivationDetails($code, $mac)
    {
        // Check users_activecode table first
        $userActiveCodes = DB::connection('mysql2')
            ->table('users_activecode')
            ->where('username', $code)
            ->get();

        if ($userActiveCodes->isNotEmpty()) {
            foreach ($userActiveCodes as $userCode) {
                // Return appropriate record based on type
                switch ($userCode->typecode) {
                    case "1": // MAC-specific
                        return DB::connection('mysql2')
                            ->table('users_activecode')
                            ->where('username', $code)
                            ->where('macadress', $mac)
                            ->first();
                    case "2":
                    case "3": // Non-MAC specific
                        return DB::connection('mysql2')
                            ->table('users_activecode')
                            ->where('username', $code)
                            ->first();
                }
            }
        }

        // Fallback to users table
        return DB::connection('mysql2')
            ->table('users')
            ->where('username', $code)
            ->first();
    }

    private function isAccountExpired($code)
    {
        $user = DB::connection('mysql2')
            ->table('users')
            ->where('username', $code)
            ->first();

        return $user && $user->macadress !== "" && time() > $user->exp_date;
    }

    private function processActivation($code, $mac, $userDetails, $codeRecord)
    {
        if (!$userDetails) {
            return $this->errorResponse('Invalid Account');
        }

        $now = time();
        $duration = $userDetails->duration_p ?? 0;
        $durationUnit = $userDetails->duration_in ?? 'days';
        $expireDate = strtotime("+{$duration} {$durationUnit}");
        $stringExpireDate = date("Y-m-d H:i:s", $expireDate);

        $isNewActivation = false;

        // Handle different activation types
        switch ($userDetails->typecode) {
            case "2": // Type 2 activation
                $isNewActivation = $this->activateType2($code, $mac, $userDetails, $now, $expireDate, $codeRecord);
                break;
            case "1": // Type 1 activation
                $isNewActivation = $this->activateType1($code, $mac, $userDetails, $now, $expireDate, $codeRecord);
                break;
            case "3": // Type 3 activation
                $isNewActivation = $this->activateType3($code, $mac, $userDetails, $now, $expireDate, $codeRecord);
                break;
        }

        // Verify activation success
        $activatedUser = $this->getActivatedUser($code, $mac, $userDetails->typecode);
        if (!$activatedUser) {
            return $this->errorResponse('Please call customer care!');
        }

        $message = $isNewActivation
            ? 'Your Account is now Active until: ' . date("Y-m-d", $activatedUser->exp_date)
            : 'Welcome, your account is already activated';

        return [
            'account' => '1',
            'status' => '100',
            'message' => $message,
            'package_id' => $userDetails->package_id,
            'exp' => date("Y-m-d H:i:s", $activatedUser->exp_date)
        ];
    }

    private function activateType2($code, $mac, $userDetails, $now, $expireDate, $codeRecord)
    {
        $needsActivation = ($userDetails->exp_date == null || $userDetails->exp_date == 0) ||
            ($userDetails->exp_date != null && ($userDetails->macadress == "" || $userDetails->macadress == "Mac Reseted"));

        if (!$needsActivation) {
            return false; // Already activated
        }

        // Update users_activecode
        DB::connection('mysql2')->table('users_activecode')
            ->where('username', $code)
            ->update([
                'macadress' => $mac,
                'exp_date' => $expireDate,
                'created_at' => $now,
            ]);

        // Create user record if doesn't exist
        $this->createUserIfNotExists($code, $mac, $now, $expireDate);

        // Update code record
        if ($codeRecord) {
            $codeRecord->update([
                'mac' => $mac,
                'time' => date("Y-m-d H:i:s", $expireDate),
            ]);
        }

        return true;
    }

    private function activateType1($code, $mac, $userDetails, $now, $expireDate, $codeRecord)
    {
        if ($userDetails->exp_date != null) {
            return false; // Already activated
        }

        // Update users_activecode
        DB::connection('mysql2')->table('users_activecode')
            ->where('username', $code)
            ->where('macadress', $mac)
            ->update([
                'exp_date' => $expireDate,
                'created_at' => $now
            ]);

        // Create user record if doesn't exist
        $this->createUserIfNotExists($code, $userDetails->macadress, $now, $expireDate);

        // Update master code if exists
        if ($codeRecord instanceof MasterCode) {
            $codeRecord->update(['time' => date("Y-m-d H:i:s", $expireDate)]);
        }

        return true;
    }

    private function activateType3($code, $mac, $userDetails, $now, $expireDate, $codeRecord)
    {
        $needsActivation = ($userDetails->exp_date == null) ||
            ($userDetails->exp_date != null && $userDetails->macadress == "");

        if (!$needsActivation) {
            return false; // Already activated
        }

        // Update users_activecode
        DB::connection('mysql2')->table('users_activecode')
            ->where('username', $code)
            ->update([
                'macadress' => $mac,
                'exp_date' => $expireDate,
                'created_at' => $now,
            ]);

        // Create user record if doesn't exist
        $expireDateToUse = ($userDetails->exp_date == null) ? $expireDate : $userDetails->exp_date;
        $this->createUserIfNotExists($code, $mac, $now, $expireDateToUse);

        // Update multi code if exists
        if ($codeRecord instanceof MultiCode) {
            $codeRecord->update([
                'mac' => $mac,
                'time' => date("Y-m-d H:i:s", $expireDate),
            ]);
        }

        return true;
    }

    private function createUserIfNotExists($code, $mac, $createdAt, $expireDate)
    {
        $userExists = DB::connection('mysql2')
            ->table('users')
            ->where('username', $code)
            ->exists();

        if ($userExists) {
            return;
        }

        $userActiveCode = DB::connection('mysql2')
            ->table('users_activecode')
            ->where('username', $code)
            ->first();

        if (!$userActiveCode) {
            return;
        }

        DB::connection('mysql2')->table('users')->insert([
            'member_id' => $userActiveCode->member_id,
            'created_by' => $userActiveCode->created_by,
            'username' => $userActiveCode->username,
            'password' => $userActiveCode->password,
            'admin_notes' => $userActiveCode->admin_notes,
            'reseller_notes' => $userActiveCode->reseller_notes,
            'package_id' => $userActiveCode->package_id,
            'bouquet' => $userActiveCode->bouquet,
            'is_trial' => $userActiveCode->is_trial,
            'allowed_ips' => '',
            'allowed_ua' => '',
            'created_at' => $createdAt,
            'exp_date' => $expireDate,
            'macadress' => $mac,
            'output' => '["m3u8","ts","rtmp"]'
        ]);
    }

    private function getActivatedUser($code, $mac, $typeCode)
    {
        if (in_array($typeCode, ["2", "3"])) {
            return DB::connection('mysql2')
                ->table('users')
                ->where('username', $code)
                ->first();
        }

        return DB::connection('mysql2')
            ->table('users')
            ->where('username', $code)
            ->where('macadress', $mac)
            ->first();
    }

    private function getChannelData($code, $mac)
    {
        $user = DB::connection('mysql2')
            ->table('users')
            ->where('username', $code)
            ->where('macadress', $mac)
            ->first();

        if (!$user) {
            return [];
        }

        // Get categories
        $allCat = DB::connection('mysql2')
            ->table('streams')
            ->whereNotNull('category_id')
            ->where('category_id', '!=', 0)
            ->distinct()
            ->pluck('category_id');

        $categoryLive = DB::connection('mysql2')
            ->table('categories')
            ->whereIn('id', $allCat)
            ->where('category_type', 'live')
            ->orderBy('cat_order')
            ->get();

        // Get channels from API
        $url = "http://atrupo4k.com:80/panel_api.php?username={$user->username}&password={$user->password}";
        $json = json_decode(file_get_contents($url), true);

        if (!$json || !isset($json['available_channels'])) {
            return [];
        }

        // Build response structure
        $resCatLive = [];
        $resLive = [];

        foreach ($categoryLive as $catLive) {
            $resCatLive[] = [
                'id' => $catLive->id,
                'caption' => $catLive->category_name,
                'icon_url' => $catLive->category_image,
            ];
        }

        foreach ($json['available_channels'] as $channel) {
            if ($channel['stream_type'] == "live") {
                $resLive[] = [
                    'icon_url' => $channel['stream_icon'],
                    'caption' => $channel['name'],
                    'category_id' => $channel['category_id'],
                    'streaming_url' => "http://atrupo4k.com:80/{$user->username}/{$user->password}/{$channel['stream_id']}"
                ];
            }
        }

        return [
            'tv_categories' => ['tv_category' => $resCatLive],
            'tv_channel' => $resLive
        ];
    }

    private function errorResponse($message)
    {
        return Response()->json([
            'account' => '0',
            'status' => '550',
            'message' => "Error. {$message}",
            'package_id' => '01',
            'exp' => '0'
        ]);
    }


    // ######################################################    function Run    ########################################################################################################


    public function Run()
    {

        if (isset($_GET['a'])) {


            // GET Variable with request ###############################################################################

            $code = $_GET["a"];
            $mac = $_GET["m"];
            $type = $_GET["p"];



            // Find of User with Code  ###############################################################################

            $user = DB::connection('mysql2')->table('users')->select('users.*')->where('username', $code)
                ->where('macadress', $mac)->first();


            if ($user) {





                $streams_category_id = DB::connection('mysql2')->table('streams')->where('streams.category_id', '!=', null)->where('streams.category_id', '!=', 0)->distinct()->get('streams.category_id');


                $allcat = [];
                foreach ($streams_category_id as $cat_id) {
                    $allcat[] = $cat_id->category_id;

                }

                // channelsList ###############################################################################

                if ($type == 'channelsList') {




                    if (isset($_GET['id']) && $_GET['id'] != '') {


                        $url = 'http://atrupo4k.com:80/panel_api.php?username=' . $user->username . '&password=' . $user->password;


                        $json = json_decode(file_get_contents($url), true);

                        $res_live = [];
                        foreach ($json['available_channels'] as $channel) {
                            if ($channel['category_id'] == $_GET['id'] && $channel['stream_type'] == "live") {
                                $as['id'] = $channel['num'];
                                $as['stream_id'] = $channel['stream_id'];
                                $as['icon_url'] = $channel['stream_icon'];
                                $as['caption'] = $channel['name'];
                                $as['category_name'] = $channel['category_name'];
                                $as['type'] = $channel['type_name'];
                                $as['streaming_url'] = "http://atrupo4k.com:80/" . $user->username . "/" . $user->password . "/" . $channel['stream_id'];
                                $as['streaming_url_m3u8'] = "http://atrupo4k.com:80/live/" . $user->username . "/" . $user->password . "/" . $channel['stream_id'] . ".m3u8";
                                // $as['tv_categories'] = [];
                                // $as['tv_categories'][]['tv_category_id'] = $channel['category_id'];
                                $as['category_id'] = $channel['category_id'];


                                $res_live[] = $as;
                            }

                        }

                        $obj = [];

                        $obj['tv_channel'] = $res_live;




                        return Response()->json($obj);



                    } else {



                        $url = 'http://atrupo4k.com:80/panel_api.php?username=' . $user->username . '&password=' . $user->password;
                        $category_live = DB::connection('mysql2')->table('categories')->whereIn('categories.id', $allcat)->where('categories.category_type', '=', 'live')
                            ->orderBy('categories.cat_order')->get();
                        $json = json_decode(file_get_contents($url), true);
                        $programme = [];

                        $res_cat_live = [];

                        if (!isset($_GET['type'])) {

                            foreach ($category_live as $catLive) {
                                $rr['id'] = $catLive->id;
                                $rr['caption'] = $catLive->category_name;
                                $rr['icon_url'] = $catLive->category_image;

                                $res_live = [];
                                foreach ($json['available_channels'] as $channel) {
                                    if ($channel['category_id'] == $catLive->id && $channel['stream_type'] == "live") {
                                        $as['icon_url'] = $channel['stream_icon'];
                                        $as['caption'] = $channel['name'];
                                        $as['streaming_url'] = "http://atrupo4k.com:80/" . $user->username . "/" . $user->password . "/" . $channel['stream_id'];
                                        $as['streaming_url_m3u8'] = "http://atrupo4k.com:80/live/" . $user->username . "/" . $user->password . "/" . $channel['stream_id'] . ".m3u8";
                                        $as['epg_channel_id'] = $channel['epg_channel_id'];

                                        $res_live[] = $as;
                                    }
                                }
                                $rr['tv_channel'] = $res_live;
                                $res_cat_live[] = $rr;
                            }
                            $obj = [];
                            $obj['tv_categories'] = [];
                            $obj['tv_categories']['tv_category'] = $res_cat_live;
                        } else {
                            foreach ($category_live as $catLive) {
                                $rr['id'] = $catLive->id;
                                $rr['caption'] = $catLive->category_name;
                                $rr['icon_url'] = $catLive->category_image;
                                $res_live = [];
                                $res_cat_live[] = $rr;
                            }
                            foreach ($json['available_channels'] as $channel) {
                                if ($channel['stream_type'] == "live") {
                                    $as['icon_url'] = $channel['stream_icon'];
                                    $as['caption'] = $channel['name'];
                                    $as['category_id'] = $channel['category_id'];
                                    $as['streaming_url'] = "http://atrupo4k.com:80/" . $user->username . "/" . $user->password . "/" . $channel['stream_id'];
                                    // $as['streaming_url_m3u8'] = "http://atrupo4k.com:80/live/".$user->username."/".$user->password."/".$channel['stream_id'].".m3u8";

                                    $res_live[] = $as;
                                }
                            }
                            $rr['tv_channel'] = $res_live;
                            $obj = [];
                            $obj['tv_categories'] = [];

                            $obj['tv_categories']['tv_category'] = $res_cat_live;
                            $obj['tv_channel'] = $res_live;
                        }
                        return Response()->json($obj);
                    }




                    // channels Vod ###############################################################################


                } else if ($type == 'Movie') {

                    if (isset($_GET['id']) && $_GET['id'] != '') {



                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_URL, 'http://atrupo4k.com:80/panel_api.php?username=' . $user->username . '&password=' . $user->password);
                        $result = curl_exec($ch);
                        curl_close($ch);
                        $json = json_decode(($result), true);


                        $res_live = [];
                        $res_Vod = [];
                        if ($json['available_channels']) {
                            foreach ($json['available_channels'] as $channel) {
                                //   dd($channel); 
                                if ($channel['category_id'] == $_GET['id'] && $channel['stream_type'] == "movie") {
                                    // if($channel['stream_type'] == "movie"){
                                    $bb['id'] = $channel['num'];
                                    $bb['stream_id'] = $channel['stream_id'];
                                    $bb['poster_url'] = $channel['stream_icon'];
                                    $bb['caption'] = $channel['name'];
                                    $bb['category_name'] = $channel['category_name'];
                                    $bb['type'] = $channel['type_name'];
                                    $bb['ext'] = $channel['container_extension'];
                                    $bb['streaming_url'] = "http://atrupo4k.com:80/movie/" . $user->username . "/" . $user->password . "/" . $channel['stream_id'] . "." . $channel['container_extension'];
                                    // $bb['tv_categories'] = [];
                                    // $bb['tv_categories'][]['tv_category_id'] = $channel['category_id'];
                                    $bb['category_id'] = $channel['category_id'];

                                    if ($channel['container_extension'] != "avi") {
                                        $res_Vod[] = $bb;
                                    }
                                }
                            }
                        }
                        $obj = [];

                        $obj['movies'] = $res_Vod;



                        return Response()->json($obj);

                    } else {

                        $category_movie = DB::connection('mysql2')->table('categories')->whereIn('categories.id', $allcat)
                            ->where('categories.category_type', '=', 'movie')->orderBy('categories.cat_order')->get();

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_URL, 'http://atrupo4k.com:80/panel_api.php?username=' . $user->username . '&password=' . $user->password);
                        $result = curl_exec($ch);
                        curl_close($ch);
                        $json = json_decode(($result), true);

                        // dd($category_movie);


                        // Stock all  Category de type Movie in new array   ###############################################################################

                        $res_cat_Movie = [];

                        foreach ($category_movie as $catMovie) {
                            $rr['id'] = $catMovie->id;
                            $rr['caption'] = $catMovie->category_name;
                            $rr['icon_url'] = $catMovie->category_image;

                            $res_cat_Movie[] = $rr;
                        }

                        // dd($res_cat_Movie);

                        // Stock all  channel in new array   ###############################################################################



                        // dd($user->password);


                        $obj = [];
                        $obj['vod_categories'] = [];

                        $obj['vod_categories']['vod_category'] = $res_cat_Movie;

                        $res_live = [];
                        $res_Vod = [];
                        if ($json['available_channels']) {
                            foreach ($json['available_channels'] as $channel) {
                                //   dd($channel); 
                                if ($channel['stream_type'] == "movie") {
                                    // if($channel['stream_type'] == "movie"){
                                    $bb['id'] = $channel['num'];
                                    // $bb['stream_id']   = $channel['stream_id'];
                                    $bb['poster_url'] = $channel['stream_icon'];
                                    $bb['caption'] = $channel['name'];
                                    // $bb['category_name'] = $channel['category_name'];
                                    // $bb['type'] = $channel['type_name'];
                                    // $bb['ext'] = $channel['container_extension'];
                                    $bb['streaming_url'] = "http://atrupo4k.com:80/movie/" . $user->username . "/" . $user->password . "/" . $channel['stream_id'] . "." . $channel['container_extension'];
                                    // $bb['tv_categories'] = [];
                                    // $bb['tv_categories'][]['tv_category_id'] = $channel['category_id'];
                                    $bb['category_id'] = $channel['category_id'];

                                    if ($channel['container_extension'] != "avi") {
                                        $res_Vod[] = $bb;
                                    }
                                }
                            }
                        }
                        $obj['movies'] = $res_Vod;


                        return Response()->json($obj);
                    }


                }
                // channels series ###############################################################################
                else if ($type == 'Series') {


                    if (isset($_GET['id']) && $_GET['id'] != '') {

                        $episodes = DB::connection('mysql2')->table('series_episodes')->where('series_episodes.series_id', $_GET['id'])
                            ->join('streams', 'series_episodes.stream_id', '=', 'streams.id')->select('series_episodes.*', 'streams.id', 'streams.target_container')
                            ->get();

                        // dd($episodes);

                        $oneserie = DB::connection('mysql2')->table('series')->where('series.id', $_GET['id'])->first();

                        foreach ($episodes as $ep) {

                            $ee['season_num'] = $ep->season_num;
                            $ee['series_id'] = $ep->series_id;
                            $ee['stream_id'] = $ep->stream_id;
                            $ee['sort'] = $ep->sort;
                            $targetContainer = trim($ep->target_container, "[");
                            $targetContainer = trim($targetContainer, "]");
                            $targetContainer = trim($targetContainer, "\"");
                            $ee['url'] = "http://atrupo4k.com:80/series/" . $user->username . "/" . $user->password . "/" . $ep->stream_id . '.' . $targetContainer;
                            $eps[] = $ee;
                            $array[] = $ep->season_num;
                            $nbrSeason = array_unique($array);
                            sort($nbrSeason);
                            $sizearray = array_values($nbrSeason);
                        }

                        return response()->json([
                            'title' => $oneserie->title,
                            'desc' => $oneserie->plot,
                            'genre' => $oneserie->genre,
                            'rating' => $oneserie->rating,
                            'releaseDate' => $oneserie->releaseDate,
                            'director' => $oneserie->director,
                            'cast' => $oneserie->cast,
                            'time' => $oneserie->episode_run_time,
                            'season' => $sizearray,
                            'series' => $eps,
                        ]);

                    } else {

                        $package = DB::connection('mysql2')->table('packages')->find($user->package_id);
                        $decode = json_decode($package->bouquets);

                        // $package = DB::connection('mysql2')->table('bouquets')->get();

                        $streams_series = DB::connection('mysql2')->table('series')->get();
                        // dd($streams_series);

                        $azz = [];

                        foreach ($streams_series as $sr) {
                            $qs['id'] = $sr->id;
                            $qs['title'] = $sr->title;
                            $qs['cover_big'] = $sr->cover;
                            $qs['category_id'] = $sr->category_id;
                            // $qs['plot'] = $sr->plot;

                            $azz[] = $qs;
                        }

                        return response()->json($azz);

                    }






                } else if ($type == 'Radio') {
                    $streams_category_id = DB::connection('mysql2')->table('streams')->where('streams.category_id', '!=', null)->where('streams.category_id', '!=', 0)->distinct()->get('streams.category_id');

                    // Stock all  IDS of category_id in Table  ###############################################################################

                    $allcat = [];
                    foreach ($streams_category_id as $cat_id) {
                        //   $tt['id'] = $cat_id->category_id;
                        $allcat[] = $cat_id->category_id;

                    }

                    $streams_series = DB::connection('mysql2')->table('streams')->where('streams.type', '=', 4)->get();

                    // dd($streams_vod[0]);

                    // GET  all category de Type Live   ###############################################################################

                    $category_series = DB::connection('mysql2')->table('categories')->whereIn('categories.id', $allcat)->where('categories.category_type', '=', 'live')->get();

                    // dd($category_movie);


                    // Stock all  Category de type Movie in new array   ###############################################################################

                    $res_cat_radio = [];

                    foreach ($category_series as $catseries) {
                        $ra['id'] = $catseries->id;
                        $ra['caption'] = $catseries->category_name;
                        // $ra['icon_url'] = $catseries->category_image;

                        $res_cat_radio[] = $ra;
                    }

                    // dd($res_cat_Movie);

                    // Stock all  channel in new array   ###############################################################################

                    $res_series = [];
                    foreach ($streams_series as $st_series) {
                        $ba['id'] = $st_series->id;
                        $ba['caption'] = $st_series->stream_display_name;
                        $ba['poster_url'] = $st_series->stream_icon;
                        $ba['num_past_epg_days'] = '2';
                        $ba['ext'] = $st_series->target_container;
                        $ba['v_url'] = "http://atrupo4k.com:80/" . $user->username . "/" . $user->password . "/" . $st_series->id;
                        $ba['v_url_m3u8'] = "http://atrupo4k.com:80/live/" . $user->username . "/" . $user->password . "/" . $st_series->id . ".m3u8";
                        $ba['radio_category'] = [];
                        $ba['radio_category'][]['radio_category_id'] = $st_series->category_id;
                        $ba['genere'] = 'N/A';
                        $res_series[] = $ba;
                    }

                    // dd($res_series);


                    $obj = [];
                    $obj['radio_categories'] = [];

                    $obj['radio_categories']['radio_category'] = $res_cat_radio;
                    $obj['radio'] = $res_series;


                    // return json_encode($obj);

                    return Response()->json($obj);
                }
            } else {
                return Response()->json([
                    'status' => '550',
                    'message' => 'Error. Please call customer care ! oki',
                    'account' => '0',
                    'package_id' => '01',
                ]);
            }

        } else {
            return Response()->json([
                'Msg ID' => '2',
                'Status' => '201',
                'Msg' => 'Your software is at latest version',
                'Link' => 'http://www.ndasat.com/soft/enigma2-plugin-extensions-ndatv_2.1_all.ipk',
            ]);

        }

    }

    public function channel()
    {

        // $code = '19091244';
        $code = $_GET['a'];

        $user = DB::connection('mysql2')->table('users')->select('users.*')->where('username', $code)->first();

        $playlist = [];
        $arraySerie = [];
        $i = 0;
        $id = 1;
        // $pls_header = "#EXTM3U";


        if ($user) {
            $json = json_decode(file_get_contents('http://atrupo4k.com:80/panel_api.php?username=' . $code . '&password=' . $user->password), true);
            // return $json;

            foreach ($json['available_channels'] as $channel) {
                $as['id'] = $channel['num'];
                $as['logo'] = $channel['stream_icon'];
                $as['name'] = $channel['name'];
                $as['category_name'] = $channel['category_name'];
                $as['type'] = $channel['type_name'];
                $as['category_id'] = $channel['category_id'];

                $playlist[] = $as;

            }

            foreach ($playlist as $tt) {

                if ($tt['type'] === 'Movies') {

                    $arraySerie[] = $tt;

                }
                $i++;

            }

            return $arraySerie;
        } else {
            return 'invalid account';
        }


    }

    public function notifications()
    {
        $Notification = Notification::all();
        return Response()->json($Notification);

    }




    function getPassword(Request $request)
    {
        $user = DB::connection('mysql2')->table('users')
            ->where('username', $request->a)
            ->first();
        if ($user) {
            return response()->json(["success" => true, "password" => $user->password]);
        } else {
            return response()->json(["success" => false, "error" => "not found", "s" => $request->a]);
        }
    }

}