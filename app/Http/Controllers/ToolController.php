<?php

namespace App\Http\Controllers;

use App\Models\ToolSetting;
use App\Services\ToolAccess;
use App\Services\ToolContent;
use Illuminate\View\View;

class ToolController extends Controller
{
    public function show(string $tool, ToolAccess $access): View
    {
        abort_unless($definition = config("tools.$tool"), 404);
        abort_unless($access->enabled($tool), 404);

        $content = app(ToolContent::class)->for($tool, $definition);
        $related = collect(config('tools'))->filter(fn ($item, $slug) => $slug !== $tool && $access->enabled($slug) && $item['category'] === $definition['category'])->take(3);
        $maxUploadKb = ToolSetting::find($tool)?->max_upload_kb ?? config('image-tools.max_upload_kb');

        return view(($definition['phase'] ?? 1) === 2 ? 'tools.phase2' : 'tools.show', compact('tool', 'definition', 'content', 'related', 'maxUploadKb'));
    }
}
