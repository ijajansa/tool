<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\ProcessingTask;
use App\Models\ToolSetting;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ToolAccess
{
    public function enabled(string $tool): bool
    {
        if (! array_key_exists($tool, config('tools'))) {
            return false;
        }

        return ! Schema::hasTable('tool_settings') || ToolSetting::find($tool)?->enabled !== false;
    }

    public function admit(string $tool, string $ip): void
    {
        abort_unless($this->enabled($tool), 404);
        $free = @disk_free_space(storage_path('app/private'));
        if ($free !== false && $free < config('image-tools.min_free_disk_mb') * 1024 * 1024) {
            throw new HttpException(503, 'Processing is temporarily unavailable because storage capacity is low.');
        }
        $hash = hash_hmac('sha256', $ip, config('app.key'));
        $active = ProcessingTask::where('ip_hash', $hash)->whereIn('status', [TaskStatus::Pending, TaskStatus::Processing])->count();
        if ($active >= config('image-tools.max_concurrent_tasks')) {
            throw new HttpException(429, 'Please wait for an existing task to finish before starting another.');
        }
    }
}
