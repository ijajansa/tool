<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\DynamicQrController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Phase2TaskController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ToolController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/categories', [PublicPageController::class, 'categories'])->name('categories.index');
Route::get('/categories/{category}', [PublicPageController::class, 'category'])->name('categories.show');
Route::get('/legal/{page}', [PublicPageController::class, 'legal'])->name('legal.show');
Route::get('/sitemap.xml', [PublicPageController::class, 'sitemap'])->name('sitemap');
Route::get('/health', [PublicPageController::class, 'health'])->name('health');
Route::get('/tools/{tool}', [ToolController::class, 'show'])->name('tools.show');

Route::middleware(['throttle:image-tools', 'captcha'])->group(function () {
    Route::post('/tools/{tool}/tasks', [TaskController::class, 'store'])->middleware('tool.admit')->name('tasks.store');
    Route::post('/phase-2/{tool}/tasks', [Phase2TaskController::class, 'store'])->middleware('tool.admit')->name('phase2.tasks.store');
    Route::post('/qr/dynamic', [DynamicQrController::class, 'store'])->middleware('throttle:qr-create')->name('qr.store');
});
Route::get('/tasks/{task}', [TaskController::class, 'status'])->name('tasks.status');
Route::get('/tasks/{task}/download', DownloadController::class)->name('tasks.download');
Route::get('/q/{code}', [DynamicQrController::class, 'redirect'])->middleware('throttle:qr-scan')->name('qr.redirect');
Route::get('/qr/{code}/analytics', [DynamicQrController::class, 'analytics'])->name('qr.analytics');
Route::get('/qr/{code}/image', [DynamicQrController::class, 'image'])->name('qr.image');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'store'])->middleware('throttle:5,1')->name('admin.login.store');
});
Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('admin.logout');
    Route::put('/tools/{tool}', [AdminDashboardController::class, 'updateTool'])->name('admin.tools.update');
    Route::post('/tasks/{task}/retry', [AdminDashboardController::class, 'retry'])->name('admin.tasks.retry');
    Route::post('/cleanup', [AdminDashboardController::class, 'cleanup'])->name('admin.cleanup');
});
