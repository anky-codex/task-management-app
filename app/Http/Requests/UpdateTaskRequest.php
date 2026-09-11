<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Full task edit — title/description/priority/due_date/reassignment.
 *
 * Deliberately excludes `status`: status changes always go through
 * PATCH /tasks/{task}/status (see UpdateTaskStatusRequest +
 * TaskWorkflowService) so there is exactly one code path that can change a
 * task's status, instead of the rules being split (and potentially
 * inconsistent) across two endpoints.
 */
class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Object-level authorization (must be the creating manager) is
        // enforced via TaskPolicy in the controller.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
            'user_id' => [
                'sometimes',
                'integer',
                Rule::exists('users', 'id')->where('role', User::ROLE_MEMBER),
            ],
        ];
    }
}
