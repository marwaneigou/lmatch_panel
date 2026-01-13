<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class LogLoginRequests
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($request->isMethod('post') && $request->is('login')) {

            $log = [
                'URI' => $request->fullUrl(),
                'METHOD' => $request->method(),
                'REQUEST_BODY' => $request->except(['password', 'g-recaptcha-response', "_token"]),
                'STATUS' => $response->getStatusCode(),
                'IP_ADDRESS' => $request->server('HTTP_CF_CONNECTING_IP'),
                'USER_AGENT' => $request->header('user-agent'),
                'LOG_TYPE' => 'LOGIN POST',
            ];

            Log::info(json_encode($log));
        }

        return $response;
    }
}