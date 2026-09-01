<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessImageTask;
use App\Jobs\ProcessPhase2Task;
use App\Models\DynamicQrScan;
use App\Models\ProcessingTask;
use App\Models\ToolSetting;
use App\Services\ImageAiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(ImageAiClient $ai): View
    {
        $counts = ProcessingTask::selectRaw('status, COUNT(*) total')->groupBy('status')->pluck('total', 'status');
        $popular = ProcessingTask::selectRaw('tool, COUNT(*) total')->groupBy('tool')->orderByDesc('total')->limit(8)->get();
        $settings = collect(config('tools'))->map(fn ($tool, $slug) => ToolSetting::firstOrNew(['tool' => $slug], ['enabled' => true]));
        $storage = collect(Storage::disk('local')->allFiles('tasks'))->sum(fn ($file) => Storage::disk('local')->size($file));
        $recentErrors = ProcessingTask::where('status', TaskStatus::Failed)->latest()->limit(8)->get(['id', 'tool', 'error_code', 'created_at']);
        $queue = DB::table('jobs')->count();

        return view('admin.dashboard', compact('counts', 'popular', 'settings', 'storage', 'recentErrors', 'queue') + ['ai' => $ai->available(), 'qrScans' => DynamicQrScan::count()]);
    }

    public function updateTool(Request $request, string $tool): RedirectResponse
    {
        abort_unless(array_key_exists($tool, config('tools')), 404);
        $data = $request->validate(['enabled' => ['nullable', 'boolean'], 'rate_limit' => ['nullable', 'integer', 'between:1,1000'], 'max_upload_kb' => ['nullable', 'integer', 'between:100,102400']]);
        ToolSetting::updateOrCreate(['tool' => $tool], ['enabled' => $request->boolean('enabled'), 'rate_limit' => $data['rate_limit'] ?? null, 'max_upload_kb' => $data['max_upload_kb'] ?? null]);

        return back()->with('status', 'Tool settings updated.');
    }

    public function retry(ProcessingTask $task): RedirectResponse
    {
        abort_unless($task->status === TaskStatus::Failed && $task->expires_at->isFuture(), 422);
        $task->update(['status' => TaskStatus::Pending, 'error_code' => null, 'error_message' => null, 'started_at' => null, 'completed_at' => null]);
        (($task->tool && (config("tools.{$task->tool}.phase") ?? 1) === 2) ? ProcessPhase2Task::class : ProcessImageTask::class)::dispatch($task->id);

        return back()->with('status', 'Safe retry queued.');
    }

    public function cleanup(): RedirectResponse
    {
        Artisan::call('images:cleanup');

        return back()->with('status', trim(Artisan::output()));
    }
}
