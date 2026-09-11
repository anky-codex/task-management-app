<?php

namespace Tests\Unit;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TaskWorkflowService $workflow;

    protected User $manager;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflow = new TaskWorkflowService;
        $this->manager = User::factory()->manager()->create();
        $this->member = User::factory()->member()->create();
    }

    protected function makeTask(string $status = Task::STATUS_TODO): Task
    {
        return Task::factory()->createdBy($this->manager)->assignedTo($this->member)->create([
            'status' => $status,
        ]);
    }

    public function test_assignee_can_move_one_step_forward(): void
    {
        $task = $this->makeTask(Task::STATUS_TODO);

        $result = $this->workflow->transition($task, Task::STATUS_IN_PROGRESS, $this->member);

        $this->assertEquals(Task::STATUS_IN_PROGRESS, $result->status);
    }

    public function test_assignee_cannot_skip_a_stage(): void
    {
        $task = $this->makeTask(Task::STATUS_TODO);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->workflow->transition($task, Task::STATUS_UNDER_QA, $this->member);
    }

    public function test_assignee_cannot_move_backward(): void
    {
        $task = $this->makeTask(Task::STATUS_UNDER_QA);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->workflow->transition($task, Task::STATUS_IN_PROGRESS, $this->member);
    }

    public function test_assignee_cannot_move_a_terminal_submitted_task(): void
    {
        $task = $this->makeTask(Task::STATUS_SUBMITTED);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->workflow->transition($task, Task::STATUS_TODO, $this->member);
    }

    public function test_unrelated_user_cannot_transition_the_task(): void
    {
        $task = $this->makeTask(Task::STATUS_TODO);
        $bystander = User::factory()->member()->create();

        $this->expectException(InvalidStatusTransitionException::class);

        $this->workflow->transition($task, Task::STATUS_IN_PROGRESS, $bystander);
    }

    public function test_creating_manager_can_jump_to_any_status(): void
    {
        $task = $this->makeTask(Task::STATUS_TODO);

        $result = $this->workflow->transition($task, Task::STATUS_SUBMITTED, $this->manager);

        $this->assertEquals(Task::STATUS_SUBMITTED, $result->status);
    }

    public function test_a_manager_who_did_not_create_the_task_cannot_override(): void
    {
        $task = $this->makeTask(Task::STATUS_TODO);
        $otherManager = User::factory()->manager()->create();

        $this->expectException(InvalidStatusTransitionException::class);

        $this->workflow->transition($task, Task::STATUS_SUBMITTED, $otherManager);
    }

    public function test_submitted_at_is_set_when_reaching_submitted(): void
    {
        $task = $this->makeTask(Task::STATUS_DEPLOY_TO_LIVE);

        $result = $this->workflow->transition($task, Task::STATUS_SUBMITTED, $this->member);

        $this->assertNotNull($result->submitted_at);
    }

    public function test_submitted_at_is_cleared_when_manager_reopens_a_submitted_task(): void
    {
        $task = $this->makeTask(Task::STATUS_SUBMITTED);
        $task->submitted_at = now();
        $task->save();

        $result = $this->workflow->transition($task, Task::STATUS_TODO, $this->manager);

        $this->assertNull($result->submitted_at);
    }
}
