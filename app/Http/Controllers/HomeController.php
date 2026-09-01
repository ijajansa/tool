<?php

namespace App\Http\Controllers;

use App\Services\ToolAccess;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(ToolAccess $access): View
    {
        $tools = collect(config('tools'))->filter(fn ($tool, $slug) => $access->enabled($slug));

        return view('home', ['tools' => $tools, 'categories' => $tools->pluck('category')->unique()->sort()->values()]);
    }
}
