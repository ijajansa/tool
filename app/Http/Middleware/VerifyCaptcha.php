<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class VerifyCaptcha
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('image-tools.captcha.enabled')) {
            return $next($request);
        }

        $token = $request->input('cf-turnstile-response');
        if (! is_string($token) || $token === '') {
            return $this->reject($request);
        }

        try {
            $verified = Http::asForm()->timeout(5)->post(config('image-tools.captcha.verify_url'), [
                'secret' => config('image-tools.captcha.secret'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ])->json('success') === true;
        } catch (ConnectionException) {
            $verified = false;
        }

        return $verified ? $next($request) : $this->reject($request);
    }

    private function reject(Request $request): Response
    {
        $message = 'Human verification failed. Refresh the page and try again.';

        return $request->expectsJson()
            ? response()->json(['message' => $message], 422)
            : back()->withErrors(['captcha' => $message]);
    }
}
