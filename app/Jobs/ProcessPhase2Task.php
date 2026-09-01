<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\ProcessingTask;
use App\Services\Phase2Processor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPhase2Task implements ShouldQueue
{
    use Queueable;

    public int $timeout = 240;

    public int $tries = 1;

    public function __construct(public readonly string $taskId) {}

    public function handle(Phase2Processor $processor): void
    {
        $task = ProcessingTask::findOrFail($this->taskId);
        $task->update(['status' => TaskStatus::Processing, 'started_at' => now()]);
        try {
            $task->update(['status' => TaskStatus::Completed, 'output_metadata' => $processor->process($task), 'completed_at' => now()]);
        } catch (Throwable $exception) {
            Log::warning('Phase 2 processing failed', ['task' => $task->id, 'exception' => $exception]);
            $task->update(['status' => TaskStatus::Failed, 'error_code' => 'processing_failed', 'error_message' => $exception->getMessage(), 'completed_at' => now()]);
        }
    }
}
