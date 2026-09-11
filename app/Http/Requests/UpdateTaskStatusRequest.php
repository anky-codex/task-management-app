<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Who may change status (assignee vs creating manager) is enforced
        // via TaskPolicy::updateStatus in the controller; this only checks
        // the value is a real status. Whether *this* transition is legal
        // for *this* actor is TaskWorkflowService's job.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(Task::STATUSES)],
        ];
    }
}
