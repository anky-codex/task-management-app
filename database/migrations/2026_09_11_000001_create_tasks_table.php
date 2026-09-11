<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            // The manager who created (and owns) the task.
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // The team member the task is assigned to. Required: a task
            // always has an owner responsible for working it, per the brief
            // ("manager ... assign it to its team member").
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // todo -> in_progress -> under_qa -> deploy_to_live -> submitted
            $table->string('status')->default('todo');
            $table->string('priority')->default('medium'); // low | medium | high
            $table->timestamp('due_date')->nullable();

            // Stamped automatically when status becomes "submitted", cleared
            // if a manager reopens the task. Mirrors a typical "completed_at"
            // but named for this workflow's terminal state.
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Nearly every query narrows by assignee or creator first, then
            // by status/due date — keep those paths index-covered.
            $table->index(['user_id', 'status']);
            $table->index(['created_by', 'status']);
            $table->index(['due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
