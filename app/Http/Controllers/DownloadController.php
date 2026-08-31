<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\ProcessingTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadController extends Controller
{
    public function __invoke(Request $request, ProcessingTask $task): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($task->ownedByCurrentSession(), 403);
        abort_if($task->expires_at->isPast(), 410, 'This download has expired.');
        abort_unless($task->status === TaskStatus::Completed, 404);

        $index = filter_var($request->query('file'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $file = $index !== false ? ($task->output_metadata['files'][$index] ?? null) : null;
        abort_unless($file && isset($file['path'], $file['name']), 404);
        abort_unless(str_starts_with($file['path'], "tasks/{$task->id}/"), 403);
        abort_unless(Storage::disk('local')->exists($file['path']), 404);

        return response()->download(Storage::disk('local')->path($file['path']), basename($file['name']), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
