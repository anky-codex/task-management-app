@extends('layouts.app')

@section('title', $task->title)

@php
    $user = auth()->user();
    $isCreatingManager = $user->isManager() && $user->id === $task->created_by;
    $isAssignee = $user->id === $task->user_id;
    $overdue = $task->due_date && $task->due_date->isPast() && $task->status !== \App\Models\Task::STATUS_SUBMITTED;
@endphp

@section('content')
    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <h1>{{ $task->title }}</h1>
            <span class="status-pill status-{{ $task->status }}">{{ str_replace('_', ' ', $task->status) }}</span>
        </div>

        @if ($task->description)
            <p>{{ $task->description }}</p>
        @endif

        <table style="margin-top: 1rem;">
            <tr>
                <th style="width: 140px;">Assigned to</th>
                <td>{{ $task->assignee->name }}</td>
            </tr>
            <tr>
                <th>Created by</th>
                <td>{{ $task->creator->name }}</td>
            </tr>
            <tr>
                <th>Priority</th>
                <td><span class="priority-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span></td>
            </tr>
            <tr>
                <th>Due date</th>
                <td class="{{ $overdue ? 'overdue' : '' }}">
                    {{ $task->due_date?->format('M j, Y') ?? '—' }}
                    @if ($overdue) (overdue) @endif
                </td>
            </tr>
            @if ($task->submitted_at)
                <tr>
                    <th>Submitted</th>
                    <td>{{ $task->submitted_at->format('M j, Y g:ia') }}</td>
                </tr>
            @endif
        </table>

        <div class="actions-row" style="flex-wrap: wrap;">
            @if ($isAssignee && $nextStatus)
                <form method="POST" action="{{ route('web.tasks.status', $task) }}" class="inline">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $nextStatus }}">
                    <button type="submit" class="btn">Mark as "{{ str_replace('_', ' ', $nextStatus) }}"</button>
                </form>
            @elseif ($isAssignee)
                <span class="muted">This task has reached its final status.</span>
            @endif

            @if ($isCreatingManager)
                <form method="POST" action="{{ route('web.tasks.status', $task) }}" class="inline" style="display:flex; gap:0.4rem; align-items:center;">
                    @csrf
                    @method('PATCH')
                    <select name="status" style="width:auto;">
                        @foreach (\App\Models\Task::STATUSES as $status)
                            <option value="{{ $status }}" @selected($status === $task->status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn secondary">Set status</button>
                </form>

                <a href="{{ route('web.tasks.edit', $task) }}" class="btn secondary">Edit</a>

                <form method="POST" action="{{ route('web.tasks.destroy', $task) }}" class="inline"
                      onsubmit="return confirm('Delete this task?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn danger">Delete</button>
                </form>
            @endif
        </div>
    </div>

    <a href="{{ route('dashboard') }}">&larr; Back to dashboard</a>
@endsection
