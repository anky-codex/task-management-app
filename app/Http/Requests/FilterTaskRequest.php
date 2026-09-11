<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // "overdue" is a virtual filter (see Task::scopeOverdue) rather
            // than a stored status, so it's accepted here alongside the
            // real status values.
            'status' => ['sometimes', Rule::in([...Task::STATUSES, 'overdue'])],
            'priority' => ['sometimes', Rule::in(Task::PRIORITIES)],
            'sort' => ['sometimes', Rule::in([
                'due_date', '-due_date',
                'priority', '-priority',
                'created_at', '-created_at',
            ])],
            // Only meaningful for a manager narrowing their team's list —
            // the controller drops it for a member (whose list is already
            // scoped to themselves).
            'assigned_to' => ['sometimes', 'integer', 'exists:users,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
