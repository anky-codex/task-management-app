<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Encapsulates the "smart filtering" behaviour for task listings.
 *
 * Pulled out of the controller so the filtering/sorting rules can be
 * unit tested directly, against a plain Eloquent builder, without HTTP
 * or auth in the way.
 */
class TaskFilterService
{
    /**
     * Supported filters (validated upstream by FilterTaskRequest):
     *   - status:      todo | in_progress | under_qa | deploy_to_live |
     *                  submitted | overdue
     *   - priority:    low | medium | high
     *   - assigned_to: a user id (meaningful for a manager narrowing their
     *                  team's list; the controller strips it for members,
     *                  whose list is already scoped to themselves)
     *   - sort:        due_date | -due_date | priority | -priority |
     *                  created_at | -created_at ("-" prefix = descending)
     *   - per_page:    1-100, defaults to 15
     */
    public function filter(Builder $query, array $filters): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;
        $priority = $filters['priority'] ?? null;
        $assignedTo = $filters['assigned_to'] ?? null;
        $sort = $filters['sort'] ?? '-created_at';
        $perPage = (int) ($filters['per_page'] ?? 15);

        if ($status === 'overdue') {
            $query->overdue();
        } elseif ($status !== null) {
            $query->status($status);
        }

        if ($priority !== null) {
            $query->priority($priority);
        }

        if ($assignedTo !== null) {
            $query->assignedTo((int) $assignedTo);
        }

        $this->applySort($query, $sort);

        return $query->paginate($perPage)->appends($filters);
    }

    protected function applySort(Builder $query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        // Priority has a natural order (low < medium < high) that doesn't
        // match alphabetical sorting, so it needs an explicit case map
        // rather than a plain orderBy on the string column.
        if ($column === 'priority') {
            $query->orderByRaw(
                "CASE priority
                    WHEN 'low' THEN 1
                    WHEN 'medium' THEN 2
                    WHEN 'high' THEN 3
                    ELSE 0
                END ".$direction
            );

            return;
        }

        $query->orderBy($column, $direction);
    }
}
