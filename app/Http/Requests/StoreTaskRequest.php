<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only a manager creates (and assigns) tasks — see TaskPolicy::create.
        // Kept here too (not just in the controller's Gate::authorize call)
        // so a non-manager gets a 403 before validation even runs.
        return $this->user()?->isManager() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
            // The team member this task is assigned to. Restricted to
            // role=member so a manager can't "assign" a task to another
            // manager, which the workflow doesn't support (only the
            // assignee — a member — can drive it through the status flow).
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', User::ROLE_MEMBER),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'priority' => $this->priority ?? Task::PRIORITY_MEDIUM,
        ]);
    }
}
