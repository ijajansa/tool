<?php

namespace App\Providers;

use App\Models\ToolSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('image-tools', function (Request $request) {
            $tool = (string) $request->route('tool');
            $limit = ToolSetting::find($tool)?->rate_limit ?? config('image-tools.rate_limit_per_minute');

            return Limit::perMinute($limit)->by(hash_hmac('sha256', (string) $request->ip(), config('app.key')));
        });
        RateLimiter::for('qr-create', fn (Request $request) => Limit::perHour(config('phase2.dynamic_qr.create_per_hour'))->by(hash_hmac('sha256', (string) $request->ip(), config('app.key'))));
        RateLimiter::for('qr-scan', fn (Request $request) => Limit::perMinute(config('phase2.dynamic_qr.scan_per_minute'))->by($request->route('code').':'.hash_hmac('sha256', (string) $request->ip(), config('app.key'))));
    }
}
