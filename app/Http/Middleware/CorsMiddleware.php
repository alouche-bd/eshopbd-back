<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    /**
     *
     * @param  Request  $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $headers = [
            'Access-Control-Allow-Origin'      => '*',
            'Access-Control-Allow-Methods'     => 'POST, GET, OPTIONS, PUT, DELETE',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age'           => '86400',
            'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With'
        ];

        if ($request->isMethod('OPTIONS')) {
            return response()->json('{"method":"OPTIONS"}', 200, $headers);
        }

        $response = $next($request);

        // Use the underlying Symfony HeaderBag — works on both
        // Illuminate\Http\Response and Symfony BinaryFileResponse / StreamedResponse,
        // whereas $response->header(...) only exists on the former.
        foreach ($headers as $key => $value) {
            if (isset($response->headers) && method_exists($response->headers, 'set')) {
                $response->headers->set($key, $value);
            } elseif (method_exists($response, 'header')) {
                $response->header($key, $value);
            }
        }

        return $response;
    }
}
