@extends('layouts.app')

@section('title', 'Dashboard')

@php $user = auth()->user(); @endphp

@section('content')
    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <h1>
                @if ($user->isManager())
                    Tasks you've assigned
                @else
                    Tasks assigned to you
                @endif
            </h1>

            @can('create', \App\Models\Task::class)
                <a href="{{ route('web.tasks.create') }}" class="btn">+ New task</a>
            @endcan
        </div>

        @if ($tasks->isEmpty())
            <p class="empty">
                @if ($user->isManager())
                    You haven't created any tasks yet.
                @else
                    Nothing's been assigned to you yet.
                @endif
            </p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>{{ $user->isManager() ? 'Assigned to' : 'Assigned by' }}</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Due</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tasks as $task)
                        @php $overdue = $task->due_date && $task->due_date->isPast() && $task->status !== \App\Models\Task::STATUS_SUBMITTED; @endphp
                        <tr>
                            <td><a href="{{ route('web.tasks.show', $task) }}">{{ $task->title }}</a></td>
                            <td>{{ $user->isManager() ? $task->assignee->name : $task->creator->name }}</td>
                            <td><span class="status-pill status-{{ $task->status }}">{{ str_replace('_', ' ', $task->status) }}</span></td>
                            <td><span class="priority-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span></td>
                            <td class="{{ $overdue ? 'overdue' : '' }}">
                                {{ $task->due_date?->format('M j, Y') ?? '—' }}
                                @if ($overdue) (overdue) @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="margin-top: 1rem;">{{ $tasks->links() }}</div>
        @endif
    </div>
@endsection
