<?php

use App\Http\Controllers\DownloadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ToolController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/tools/{tool}', [ToolController::class, 'show'])->name('tools.show');

Route::middleware('throttle:image-tools')->group(function () {
    Route::post('/tools/{tool}/tasks', [TaskController::class, 'store'])->name('tasks.store');
});
Route::get('/tasks/{task}', [TaskController::class, 'status'])->name('tasks.status');
Route::get('/tasks/{task}/download', DownloadController::class)->name('tasks.download');
