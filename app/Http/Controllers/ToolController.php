<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ToolController extends Controller
{
    public function show(string $tool): View
    {
        abort_unless($definition = config("tools.$tool"), 404);

        return view(($definition['phase'] ?? 1) === 2 ? 'tools.phase2' : 'tools.show', compact('tool', 'definition'));
    }
}
