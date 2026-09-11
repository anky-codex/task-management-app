<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Thrown by TaskWorkflowService when a requested status change isn't a
 * legal move for the acting user. Carries its own render() so the
 * exception maps to a 422 response wherever it's thrown, without needing
 * a case in the global exception handler — or, for the web UI, redirects
 * back with the same message as a flashed validation error.
 */
class InvalidStatusTransitionException extends Exception
{
    public static function notPermitted(): static
    {
        return new static('You are not allowed to change the status of this task.');
    }

    public static function terminal(string $status): static
    {
        return new static("Task is already \"{$status}\" and cannot move forward any further.");
    }

    public static function skipped(string $from, string $to, string $expected): static
    {
        return new static(
            "Cannot move a task from \"{$from}\" to \"{$to}\" — the next step is \"{$expected}\"."
        );
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withErrors(['status' => $this->getMessage()]);
    }
}
