<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * A manager sees the tasks they created (with who it's assigned to);
     * a member sees only what's assigned to them. Same scoping rule as
     * Api\TaskController::index, just rendered instead of returned as JSON.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Task::query()->with(['assignee', 'creator'])->latest();
        $query = $user->isManager() ? $query->createdBy($user->id) : $query->assignedTo($user->id);

        $tasks = $query->paginate(15)->withQueryString();

        return view('dashboard', ['tasks' => $tasks]);
    }
}
