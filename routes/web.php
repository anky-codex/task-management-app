<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\TaskController;
use Illuminate\Support\Facades\Route;

// Session-based web UI on top of the same models/policies/services the
// JSON API (routes/api.php) uses — a manager creates a task and assigns it
// to a team member; the member logs in and works it through its statuses.

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Named "web.tasks.*", not "tasks.*" — routes/api.php's apiResource
    // already claims tasks.show/store/update/destroy/status, and a shared
    // name would make route() resolve to whichever group loaded last
    // instead of the path you meant.
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('web.tasks.status');

    Route::get('/tasks/create', [TaskController::class, 'create'])->name('web.tasks.create');
    Route::post('/tasks', [TaskController::class, 'store'])->name('web.tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('web.tasks.show');
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('web.tasks.edit');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('web.tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('web.tasks.destroy');
});
