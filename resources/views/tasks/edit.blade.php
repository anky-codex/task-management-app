@extends('layouts.app')

@section('title', 'Edit task')

@section('content')
    <div class="card" style="max-width: 560px;">
        <h1>Edit task</h1>

        <form method="POST" action="{{ route('web.tasks.update', $task) }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="{{ old('title', $task->title) }}" required autofocus>
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description">{{ old('description', $task->description) }}</textarea>
            </div>

            <div class="field">
                <label for="user_id">Assign to</label>
                <select id="user_id" name="user_id" required>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" @selected(old('user_id', $task->user_id) == $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
                    @foreach (\App\Models\Task::PRIORITIES as $priority)
                        <option value="{{ $priority }}" @selected(old('priority', $task->priority) === $priority)>{{ ucfirst($priority) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="due_date">Due date</label>
                <input type="date" id="due_date" name="due_date"
                       value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}">
            </div>

            <div class="actions-row">
                <button type="submit" class="btn">Save changes</button>
                <a href="{{ route('web.tasks.show', $task) }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
