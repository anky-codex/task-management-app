<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Web (session-auth) counterpart to Api\TaskController — same Form
 * Requests, Policy and TaskWorkflowService, rendering Blade views and
 * redirects instead of JSON.
 */
class TaskController extends Controller
{
    public function __construct(protected TaskWorkflowService $workflow) {}

    public function create(): View
    {
        Gate::authorize('create', Task::class);

        return view('tasks.create', ['members' => $this->members()]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $task = Task::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'status' => Task::STATUS_TODO,
        ]);

        return redirect()
            ->route('web.tasks.show', $task)
            ->with('status', "Task \"{$task->title}\" created and assigned.");
    }

    public function show(Task $task): View
    {
        Gate::authorize('view', $task);

        $task->load(['assignee', 'creator']);

        return view('tasks.show', [
            'task' => $task,
            'nextStatus' => TaskWorkflowService::FORWARD_FLOW[$task->status] ?? null,
        ]);
    }

    public function edit(Task $task): View
    {
        Gate::authorize('update', $task);

        return view('tasks.edit', ['task' => $task, 'members' => $this->members()]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->update($request->validated());

        return redirect()->route('web.tasks.show', $task)->with('status', 'Task updated.');
    }

    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('updateStatus', $task);

        $this->workflow->transition($task, $request->validated('status'), $request->user());

        return redirect()->route('web.tasks.show', $task)->with('status', 'Status updated.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return redirect()->route('dashboard')->with('status', "Task \"{$task->title}\" deleted.");
    }

    /** Members are the only valid assignees — see StoreTaskRequest. */
    protected function members()
    {
        return User::query()->where('role', User::ROLE_MEMBER)->orderBy('name')->get();
    }
}
