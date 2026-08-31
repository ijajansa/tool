<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\StoreToolTaskRequest;
use App\Jobs\ProcessImageTask;
use App\Models\ProcessingTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function store(StoreToolTaskRequest $request, string $tool): JsonResponse
    {
        $validated = $request->validated();
        $id = (string) Str::uuid();
        $ownerToken = $request->session()->get('image_tools_owner_token', Str::random(64));
        $request->session()->put('image_tools_owner_token', $ownerToken);
        $files = [];
        try {
            foreach ($request->file('images') as $upload) {
                $dimensions = @getimagesize($upload->getRealPath());
                if (! $dimensions || ($dimensions[0] * $dimensions[1]) > config('image-tools.max_pixels')) {
                    throw ValidationException::withMessages(['images' => 'The image is invalid or has too many pixels.']);
                }
                $extension = match ($upload->getMimeType()) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => throw ValidationException::withMessages(['images' => 'Only JPG, PNG, and WebP files are supported.']),
                };
                $path = $upload->storeAs("tasks/$id/input", Str::uuid().".$extension", 'local');
                $files[] = [
                    'path' => $path,
                    'original_name' => Str::limit(basename($upload->getClientOriginalName()), 180, ''),
                    'mime' => $upload->getMimeType(),
                    'size' => $upload->getSize(),
                    'width' => $dimensions[0],
                    'height' => $dimensions[1],
                ];
            }

            $options = collect($validated)->except('images')->all();
            $task = ProcessingTask::create([
                'id' => $id,
                'tool' => $tool,
                'status' => TaskStatus::Pending,
                'input_metadata' => ['files' => $files],
                'options' => $options,
                'session_hash' => hash('sha256', $ownerToken),
                'ip_hash' => hash_hmac('sha256', (string) $request->ip(), config('app.key')),
                'expires_at' => now()->addMinutes(config('image-tools.task_ttl_minutes')),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->deleteDirectory("tasks/$id");
            throw $exception;
        }

        ProcessImageTask::dispatch($task->id);

        return response()->json([
            'id' => $task->id,
            'status' => $task->status,
            'status_url' => route('tasks.status', $task),
        ], 202);
    }

    public function status(ProcessingTask $task): JsonResponse
    {
        abort_unless($task->ownedByCurrentSession(), 403);
        $task->refresh();
        $response = [
            'id' => $task->id,
            'status' => $task->status,
            'error' => $task->status === TaskStatus::Failed ? [
                'code' => $task->error_code,
                'message' => $task->error_message,
            ] : null,
        ];
        if ($task->status === TaskStatus::Completed) {
            $metadata = $task->output_metadata;
            $metadata['files'] = collect($metadata['files'])->map(function (array $file, int $index) use ($task) {
                unset($file['path']);
                $file['download_url'] = URL::temporarySignedRoute(
                    'tasks.download',
                    now()->addMinutes(config('image-tools.download_ttl_minutes')),
                    ['task' => $task, 'file' => $index]
                );

                return $file;
            })->values();
            $response['result'] = $metadata;
        }

        return response()->json($response);
    }
}
