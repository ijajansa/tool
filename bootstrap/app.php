<?php

use App\Http\Middleware\AdmitToolTask;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\LimitRequestSize;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyCaptcha;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([LimitRequestSize::class, SecurityHeaders::class]);
        $middleware->alias(['admin' => EnsureAdmin::class, 'tool.admit' => AdmitToolTask::class, 'captcha' => VerifyCaptcha::class]);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
