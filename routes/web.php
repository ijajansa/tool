<?php

use App\Http\Controllers\DownloadController;
use App\Http\Controllers\DynamicQrController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Phase2TaskController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ToolController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/tools/{tool}', [ToolController::class, 'show'])->name('tools.show');

Route::middleware('throttle:image-tools')->group(function () {
    Route::post('/tools/{tool}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::post('/phase-2/{tool}/tasks', [Phase2TaskController::class, 'store'])->name('phase2.tasks.store');
    Route::post('/qr/dynamic', [DynamicQrController::class, 'store'])->name('qr.store');
});
Route::get('/tasks/{task}', [TaskController::class, 'status'])->name('tasks.status');
Route::get('/tasks/{task}/download', DownloadController::class)->name('tasks.download');
Route::get('/q/{code}', [DynamicQrController::class, 'redirect'])->middleware('throttle:60,1')->name('qr.redirect');
Route::get('/qr/{code}/analytics', [DynamicQrController::class, 'analytics'])->name('qr.analytics');
Route::get('/qr/{code}/image', [DynamicQrController::class, 'image'])->name('qr.image');
