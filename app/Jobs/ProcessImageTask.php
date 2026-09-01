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

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $taskId) {}

    public function handle(ImageProcessor $processor): void
    {
        $task = ProcessingTask::findOrFail($this->taskId);
        if ($task->expires_at->isPast()) {
            return;
        }
        $task->update(['status' => TaskStatus::Processing, 'started_at' => now()]);
        try {
            $output = $processor->process($task);
            if (($output['output_size'] ?? 0) > config('image-tools.max_output_kb') * 1024) {
                throw new \RuntimeException('The generated output exceeds the configured limit.');
            }
            $task->update([
                'status' => TaskStatus::Completed,
                'output_metadata' => $output,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Image processing failed', ['task' => $task->id, 'exception_class' => $exception::class]);
            $task->update([
                'status' => TaskStatus::Failed,
                'error_code' => 'processing_failed',
                'error_message' => app()->hasDebugModeEnabled() ? $exception->getMessage() : 'We could not process this image. Please try another file.',
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        ProcessingTask::whereKey($this->taskId)->where('status', '!=', TaskStatus::Completed->value)->update(['status' => TaskStatus::Failed, 'error_code' => 'queue_failure', 'error_message' => 'Processing stopped unexpectedly. Please submit the task again.', 'completed_at' => now()]);
    }
}
