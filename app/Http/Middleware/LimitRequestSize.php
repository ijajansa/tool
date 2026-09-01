<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LimitRequestSize
{
    public function handle(Request $request, Closure $next)
    {
        $length = (int) $request->server('CONTENT_LENGTH', 0);
        abort_if($length > config('image-tools.max_request_kb') * 1024, 413, 'The request is larger than the configured limit.');

        return $next($request);
    }
}
