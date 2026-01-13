<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use Closure;
use Auth;

class LogRoute
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (app()->environment('local')) {
            $log = [
                'URI' => $request->getUri(),
                'METHOD' => $request->getMethod(),
                'REQUEST_BODY' => $request->all(),
                'RESPONSE' => $response->getContent(),
                'USER AGENT' => $request->header('user-agent'),
                'LOG_TYPE' => 'CUSTOM LOG',
                'USER_NAME' => Auth::user()->name,
                'USER_ID' => Auth::user()->id
            ];

            Log::info(json_encode($log));
        }

        return $response;
    }
}
