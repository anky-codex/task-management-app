<?php

namespace App\Services;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Task;
use App\Models\User;

/**
 * Encapsulates the task status lifecycle so the rules live in one place
 * and can be unit tested without HTTP, auth, or a controller in the way.
 *
 * Rules (documented as assumptions in the README, since the brief didn't
 * fully specify them):
 *   - A team member (the assignee) may only move a task exactly one step
 *     forward along the fixed sequence in Task::STATUSES. No skipping
 *     stages, no moving backward, nothing once it's "submitted".
 *   - The manager who created the task may set it to ANY status at ANY
 *     time — e.g. to reopen a submitted task or correct a mistake. They
 *     own the process end to end, so they're not bound by the forward-only
 *     rule that keeps a team member's changes disciplined.
 *   - Nobody else (a different manager, an unrelated member) may change
 *     the status at all.
 */
class TaskWorkflowService
{
    /** current status => the one legal next status for a team member. */
    public const FORWARD_FLOW = [
        Task::STATUS_TODO => Task::STATUS_IN_PROGRESS,
        Task::STATUS_IN_PROGRESS => Task::STATUS_UNDER_QA,
        Task::STATUS_UNDER_QA => Task::STATUS_DEPLOY_TO_LIVE,
        Task::STATUS_DEPLOY_TO_LIVE => Task::STATUS_SUBMITTED,
    ];

    /**
     * @throws InvalidStatusTransitionException
     */
    public function transition(Task $task, string $newStatus, User $actor): Task
    {
        $isCreatingManager = $actor->isManager() && $actor->id === $task->created_by;
        $isAssignee = $actor->id === $task->user_id;

        if ($isCreatingManager) {
            // Override: no further checks — a manager can set any status.
        } elseif ($isAssignee) {
            $this->assertForwardStep($task->status, $newStatus);
        } else {
            throw InvalidStatusTransitionException::notPermitted();
        }

        $task->status = $newStatus;
        $task->submitted_at = $newStatus === Task::STATUS_SUBMITTED ? now() : null;
        $task->save();

        return $task;
    }

    /**
     * @throws InvalidStatusTransitionException
     */
    protected function assertForwardStep(string $currentStatus, string $newStatus): void
    {
        $expected = self::FORWARD_FLOW[$currentStatus] ?? null;

        if ($expected === null) {
            throw InvalidStatusTransitionException::terminal($currentStatus);
        }

        if ($newStatus !== $expected) {
            throw InvalidStatusTransitionException::skipped($currentStatus, $newStatus, $expected);
        }
    }
}
