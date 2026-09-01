<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\ProcessingTask;
use Illuminate\Console\Command;

class RecoverStaleTasks extends Command
{
    protected $signature = 'tasks:recover-stale';

    protected $description = 'Mark abandoned pending or processing tasks as failed';

    public function handle(): int
    {
        $count = ProcessingTask::whereIn('status', [TaskStatus::Pending, TaskStatus::Processing])->where('updated_at', '<', now()->subMinutes(config('image-tools.stale_after_minutes')))->update(['status' => TaskStatus::Failed, 'error_code' => 'processing_timeout', 'error_message' => 'Processing timed out. Please submit the task again.', 'completed_at' => now()]);
        $this->info("Recovered $count stale task(s).");

        return self::SUCCESS;
    }
}
