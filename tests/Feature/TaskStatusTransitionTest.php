<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers PATCH /api/tasks/{task}/status — the one endpoint that can change
 * a task's status. See TaskWorkflowService for the rules under test here.
 */
class TaskStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->member = User::factory()->member()->create();
    }

    protected function authenticateAs(User $user): void
    {
        Sanctum::actingAs($user);
    }

    public function test_assignee_can_move_a_task_one_step_forward(): void
    {
        $this->authenticateAs($this->member);

        $task = Task::factory()->createdBy($this->manager)->assignedTo($this->member)->create([
            'status' => Task::STATUS_TODO,
        ]);

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => Task::STATUS_IN_PROGRESS])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_assignee_cannot_skip_a_stage(): void
    {
        $this->authenticateAs($this->member);

        $task = Task::factory()->createdBy($this->manager)->assignedTo($this->member)->create([
            'status' => Task::STATUS_TODO,
        ]);

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => Task::STATUS_UNDER_QA])
            ->assertUnprocessable();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => Task::STATUS_TODO]);
    }

    public function test_assignee_cannot_move_a_task_backward(): void
    {
        $this->authenticateAs($this->member);

        $task = Task::factory()->createdBy($this->manager)->assignedTo($this->member)->create([
            'status' => Task::STATUS_UNDER_QA,
        ]);

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => Task::STATUS_IN_PROGRESS])
            ->assertUnprocessable();
    }

    public function test_assignee_cannot_change_status_once_submitted(): void
    {
        $this->authenticateAs($this->member);

        $task = Task::factory()->createdBy($this->manager)->assignedTo($this->member)
            ->withStatus(Task::STATUS_SUBMITTED)->create();

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => Task::STATUS_TODO])
            ->assertUnprocessable();
    }

    public function test_a_user_who_is_not_the_assignee_or_creator_cannot_change_status(): void
    {
        $bystander = User::factory()->member()->create();
        $this->authenticateAs($bystander);

        $task = Task::factory()->createdBy($this->manager)->assignedTo($this->member)->create([
            'status' => Task::STATUS_TODO,
        ]);

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => Task::STATUS_IN_PROGRESS])
            ->assertForbidden();
    }

    public function test_creating_manager_can_override_status_to_any_value(): void
    {
        $this->authenticateAs($this->manager);

        $task = Task::factory()->createdBy($this->manager)->assignedTo($this->member)->create([
            'status' => Task::STATUS_TODO,
        ]);

        // Skips straight to deploy_to_live — a member could never do this,
        // but the creating manager owns the process end to end.
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => Task::STATUS_DEPLOY_TO_LIVE])
            ->assertOk()
            ->assertJsonPath('data.status', 'deploy_to_live');
    }

    public function test_a_different_manager_cannot_change_status_of_a_task_they_did_not_create(): void
    {
        $otherManager = User::factory()->manager()->create();
        $this->authenticateAs($otherManager);

        $task = Task::factory()->createdBy($this->manager)->assignedTo($this->member)->create();

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => Task::STATUS_IN_PROGRESS])
            ->assertForbidden();
    }

    public function test_submitted_at_is_stamped_when_status_becomes_submitted_and_cleared_on_reopen(): void
    {
        $this->authenticateAs($this->manager);

        $task = Task::factory()->createdBy($this->manager)->assignedTo($this->member)->create([
            'status' => Task::STATUS_DEPLOY_TO_LIVE,
        ]);

        $response = $this->patchJson("/api/tasks/{$task->id}/status", ['status' => Task::STATUS_SUBMITTED]);
        $response->assertOk();
        $this->assertNotNull($response->json('data.submitted_at'));

        // Manager reopens it — submitted_at must not linger from before.
        $response = $this->patchJson("/api/tasks/{$task->id}/status", ['status' => Task::STATUS_TODO]);
        $response->assertOk();
        $this->assertNull($response->json('data.submitted_at'));
    }

    public function test_an_unknown_status_value_is_rejected_by_validation(): void
    {
        $this->authenticateAs($this->member);

        $task = Task::factory()->createdBy($this->manager)->assignedTo($this->member)->create();

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'not_a_real_status'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }
}
