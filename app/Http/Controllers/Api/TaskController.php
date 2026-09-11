<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskFilterService;
use App\Services\TaskWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function __construct(
        protected TaskFilterService $filterService,
        protected TaskWorkflowService $workflow,
    ) {}

    /**
     * GET /api/tasks
     *
     * Supports:
     *   ?status=todo|in_progress|under_qa|deploy_to_live|submitted|overdue
     *   ?priority=low|medium|high
     *   ?sort=due_date|-due_date|priority|-priority|created_at|-created_at
     *   ?assigned_to=<user id>   (manager only — narrows their team's list)
     *   ?per_page=1..100
     *
     * There's no "view everything" role in this brief, so the base query
     * is always scoped by who's asking: a manager sees the tasks they
     * created, a team member sees only what's assigned to them. Filters
     * narrow further; they never widen past that scope.
     */
    public function index(FilterTaskRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Task::query()->with(['assignee', 'creator']);
        $query = $user->isManager() ? $query->createdBy($user->id) : $query->assignedTo($user->id);

        $filters = $request->validated();

        if (! $user->isManager()) {
            unset($filters['assigned_to']);
        }

        $tasks = $this->filterService->filter($query, $filters);

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request)
    {
        // status is never client-supplied: every task starts life as
        // "todo", set by the manager who creates and assigns it.
        $task = Task::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'status' => Task::STATUS_TODO,
        ]);

        return (new TaskResource($task->load(['assignee', 'creator'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return new TaskResource($task->load(['assignee', 'creator']));
    }

    /** Full edit (title/description/priority/due_date/reassign) — the creating manager only. */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        Gate::authorize('update', $task);

        $task->update($request->validated());

        return new TaskResource($task->load(['assignee', 'creator']));
    }

    /**
     * PATCH /api/tasks/{task}/status
     *
     * The one place a task's status can change — see TaskWorkflowService
     * for the transition rules (member: one step forward; creating
     * manager: any status, any time).
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): TaskResource
    {
        Gate::authorize('updateStatus', $task);

        $task = $this->workflow->transition($task, $request->validated('status'), $request->user());

        return new TaskResource($task->load(['assignee', 'creator']));
    }

    public function destroy(Request $request, Task $task)
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }
}
