<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /** Listing is always scoped (manager -> created tasks, member -> assigned tasks) in the controller. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $user->id === $task->created_by || $user->id === $task->user_id;
    }

    /** Only managers create tasks (and assign them to a team member). */
    public function create(User $user): bool
    {
        return $user->isManager();
    }

    /** Full edit of task details (title/description/priority/due_date/assignee) — the creating manager only. */
    public function update(User $user, Task $task): bool
    {
        return $user->isManager() && $user->id === $task->created_by;
    }

    /** Status-only transition — the assignee (forward steps) or the creating manager (override). */
    public function updateStatus(User $user, Task $task): bool
    {
        return $user->id === $task->user_id
            || ($user->isManager() && $user->id === $task->created_by);
    }

    /** Only the creating manager may delete (soft-delete) a task. */
    public function delete(User $user, Task $task): bool
    {
        return $user->isManager() && $user->id === $task->created_by;
    }
}
