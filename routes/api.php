<?php

use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

// All task routes require a valid Sanctum token. Login/registration
// endpoints are omitted since auth scaffolding isn't the focus of this
// exercise — see README for how tests authenticate and how you'd wire up
// real token issuance.
Route::middleware('auth:sanctum')->group(function () {
    // Registered ahead of the resource route: this is the only path that
    // may change `status`, kept separate from the general update() so the
    // workflow rules live in exactly one place (TaskWorkflowService).
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');

    Route::apiResource('tasks', TaskController::class);
});
