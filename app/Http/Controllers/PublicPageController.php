<?php

namespace App\Http\Controllers;

use App\Services\ImageAiClient;
use App\Services\ToolAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function categories(ToolAccess $access): View
    {
        $tools = collect(config('tools'))->filter(fn ($tool, $slug) => $access->enabled($slug));

        return view('categories.index', ['categories' => $tools->groupBy('category')]);
    }

    public function category(string $category, ToolAccess $access): View
    {
        $tools = collect(config('tools'))->filter(fn ($tool, $slug) => $access->enabled($slug) && strtolower($tool['category']) === strtolower($category));
        abort_if($tools->isEmpty(), 404);

        return view('categories.show', ['category' => $tools->first()['category'], 'tools' => $tools]);
    }

    public function legal(string $page): View
    {
        abort_unless(array_key_exists($page, config('legal')), 404);

        return view('legal.show', ['page' => $page, 'document' => config('legal.'.$page)]);
    }

    public function sitemap(ToolAccess $access)
    {
        $tools = collect(config('tools'))->filter(fn ($tool, $slug) => $access->enabled($slug));

        return response()->view('sitemap', ['tools' => $tools, 'legal' => array_keys(config('legal'))])->header('Content-Type', 'application/xml');
    }

    public function health(ImageAiClient $ai): JsonResponse
    {
        try {
            DB::select('select 1');
            $database = true;
        } catch (\Throwable) {
            $database = false;
        }
        $free = @disk_free_space(Storage::disk('local')->path(''));
        $storage = $free === false || $free >= config('image-tools.min_free_disk_mb') * 1024 * 1024;
        $ok = $database && $storage;

        return response()->json(['status' => $ok ? 'ok' : 'degraded', 'checks' => ['database' => $database, 'storage' => $storage, 'ai_optional' => $ai->available()]], $ok ? 200 : 503);
    }
}
