<?php

namespace App\Console\Commands;

use App\Models\ProcessingTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupImageTasks extends Command
{
    protected $signature = 'images:cleanup';

    protected $description = 'Delete expired image-processing files and records';

    public function handle(): int
    {
        $count = 0;
        ProcessingTask::where('expires_at', '<=', now())->chunkById(100, function ($tasks) use (&$count) {
            foreach ($tasks as $task) {
                Storage::disk('local')->deleteDirectory("tasks/{$task->id}");
                $task->delete();
                $count++;
            }
        });
        $this->info("Deleted $count expired task(s).");

        return self::SUCCESS;
    }
}
