<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_UNDER_QA = 'under_qa';

    public const STATUS_DEPLOY_TO_LIVE = 'deploy_to_live';

    public const STATUS_SUBMITTED = 'submitted';

    /**
     * Ordered lifecycle. Order matters here: TaskWorkflowService walks this
     * sequence to decide what "the next step" is for a forward transition.
     */
    public const STATUSES = [
        self::STATUS_TODO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_UNDER_QA,
        self::STATUS_DEPLOY_TO_LIVE,
        self::STATUS_SUBMITTED,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITIES = [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH];

    protected $fillable = [
        'created_by',
        'user_id',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'submitted_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    /** The team member responsible for working this task. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The manager who created (and owns) this task. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: tasks that are overdue.
     *
     * Assumption (documented in README): a task is "overdue" when its
     * due_date is in the past AND it hasn't reached the terminal
     * "submitted" state. It's computed on read rather than stored, so it
     * can never drift out of sync with the real status.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->where('status', '!=', self::STATUS_SUBMITTED);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCreatedBy(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }
}
