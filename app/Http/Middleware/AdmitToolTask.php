<?php

namespace App\Http\Middleware;

use App\Services\ToolAccess;
use Closure;
use Illuminate\Http\Request;

class AdmitToolTask
{
    public function handle(Request $request, Closure $next)
    {
        app(ToolAccess::class)->admit((string) $request->route('tool'), (string) $request->ip());

        return $next($request);
    }
}
