<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\StorePhase2TaskRequest;
use App\Jobs\ProcessPhase2Task;
use App\Models\ProcessingTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Phase2TaskController extends Controller
{
    public function store(StorePhase2TaskRequest $request, string $tool): JsonResponse
    {
        $id = (string) Str::uuid();
        $token = $request->session()->get('image_tools_owner_token', Str::random(64));
        $request->session()->put('image_tools_owner_token', $token);
        $files = [];
        try {
            foreach (['documents', 'images', 'logo'] as $field) {
                $uploads = $request->file($field, []);
                $uploads = is_array($uploads) ? $uploads : [$uploads];
                foreach (array_filter($uploads) as $upload) {
                    $mime = $upload->getMimeType();
                    $extension = match ($mime) {
                        'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => throw new \InvalidArgumentException('Unsupported uploaded file content.')
                    };
                    $path = $upload->storeAs("tasks/$id/input", Str::uuid().".$extension", 'local');
                    $files[] = ['path' => $path, 'original_name' => Str::limit(basename($upload->getClientOriginalName()), 180, ''), 'mime' => $mime, 'size' => $upload->getSize(), 'field' => $field];
                }
            }
            $task = ProcessingTask::create(['id' => $id, 'tool' => $tool, 'status' => TaskStatus::Pending, 'input_metadata' => ['files' => $files], 'options' => $request->validated('payload'), 'session_hash' => hash('sha256', $token), 'ip_hash' => hash_hmac('sha256', (string) $request->ip(), config('app.key')), 'expires_at' => now()->addMinutes(config('image-tools.task_ttl_minutes'))]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->deleteDirectory("tasks/$id");
            throw $exception;
        }
        ProcessPhase2Task::dispatch($task->id);

        return response()->json(['id' => $task->id, 'status' => $task->status, 'status_url' => route('tasks.status', $task)], 202);
    }
}
