<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\ProcessingTask;
use App\Services\ImageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessImageTask implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(public readonly string $taskId) {}

    public function handle(ImageProcessor $processor): void
    {
        $task = ProcessingTask::findOrFail($this->taskId);
        if ($task->expires_at->isPast()) {
            return;
        }
        $task->update(['status' => TaskStatus::Processing, 'started_at' => now()]);
        try {
            $task->update([
                'status' => TaskStatus::Completed,
                'output_metadata' => $processor->process($task),
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Image processing failed', ['task' => $task->id, 'exception' => $exception]);
            $task->update([
                'status' => TaskStatus::Failed,
                'error_code' => 'processing_failed',
                'error_message' => app()->hasDebugModeEnabled() ? $exception->getMessage() : 'We could not process this image. Please try another file.',
                'completed_at' => now(),
            ]);
        }
    }
}
