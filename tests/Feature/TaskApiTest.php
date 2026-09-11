<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
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

    // --- create -------------------------------------------------------

    public function test_manager_can_create_a_task_assigned_to_a_team_member(): void
    {
        $this->authenticateAs($this->manager);

        $response = $this->postJson('/api/tasks', [
            'title' => 'Write the README',
            'priority' => 'high',
            'user_id' => $this->member->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Write the README')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', 'todo') // always forced, regardless of input
            ->assertJsonPath('data.assignee.id', $this->member->id)
            ->assertJsonPath('data.created_by.id', $this->manager->id);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Write the README',
            'user_id' => $this->member->id,
            'created_by' => $this->manager->id,
            'status' => 'todo',
        ]);
    }

    public function test_client_supplied_status_is_ignored_on_create(): void
    {
        $this->authenticateAs($this->manager);

        $response = $this->postJson('/api/tasks', [
            'title' => 'Sneaky task',
            'user_id' => $this->member->id,
            'status' => 'submitted',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'todo');
    }

    public function test_team_member_cannot_create_a_task(): void
    {
        $this->authenticateAs($this->member);

        $this->postJson('/api/tasks', [
            'title' => 'Not allowed',
            'user_id' => $this->member->id,
        ])->assertForbidden();
    }

    public function test_title_is_required_to_create_a_task(): void
    {
        $this->authenticateAs($this->manager);

        $this->postJson('/api/tasks', ['user_id' => $this->member->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_cannot_assign_a_task_to_a_manager(): void
    {
        $this->authenticateAs($this->manager);

        $otherManager = User::factory()->manager()->create();

        $this->postJson('/api/tasks', [
            'title' => 'Bad assignment',
            'user_id' => $otherManager->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    // --- listing / scoping ---------------------------------------------

    public function test_manager_only_sees_tasks_they_created(): void
    {
        $this->authenticateAs($this->manager);

        Task::factory()->count(2)->createdBy($this->manager)->create();
        Task::factory()->count(3)->create(); // created by a different manager

        $response = $this->getJson('/api/tasks');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_member_only_sees_tasks_assigned_to_them(): void
    {
        $this->authenticateAs($this->member);

        Task::factory()->count(2)->assignedTo($this->member)->create();
        Task::factory()->count(3)->create(); // assigned to someone else

        $response = $this->getJson('/api/tasks');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_manager_can_narrow_by_assigned_to(): void
    {
        $this->authenticateAs($this->manager);

        $other = User::factory()->member()->create();
        Task::factory()->createdBy($this->manager)->assignedTo($this->member)->create();
        Task::factory()->createdBy($this->manager)->assignedTo($other)->create();

        $response = $this->getJson("/api/tasks?assigned_to={$this->member->id}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('assignee.id');
        $this->assertTrue($ids->every(fn ($id) => $id === $this->member->id));
    }

    public function test_assigned_to_filter_is_ignored_for_a_team_member(): void
    {
        // A member's list is already scoped to themselves; assigned_to
        // must not let them peek at someone else's tasks.
        $this->authenticateAs($this->member);

        $other = User::factory()->member()->create();
        Task::factory()->assignedTo($this->member)->create();
        Task::factory()->assignedTo($other)->create();

        $response = $this->getJson("/api/tasks?assigned_to={$other->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // --- filtering -------------------------------------------------------

    public function test_filtering_by_status_overdue_excludes_submitted_tasks(): void
    {
        $this->authenticateAs($this->manager);

        $overdue = Task::factory()->createdBy($this->manager)->overdue()->create();
        Task::factory()->createdBy($this->manager)->withStatus('submitted')->create([
            'due_date' => now()->subDays(5), // overdue date but already submitted — must be excluded
        ]);
        Task::factory()->createdBy($this->manager)->create([
            'due_date' => now()->addDays(5), // future — not overdue
        ]);

        $response = $this->getJson('/api/tasks?status=overdue');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($overdue->id));
    }

    public function test_filtering_by_priority(): void
    {
        $this->authenticateAs($this->manager);

        Task::factory()->createdBy($this->manager)->create(['priority' => 'high']);
        Task::factory()->createdBy($this->manager)->create(['priority' => 'low']);

        $response = $this->getJson('/api/tasks?priority=high');

        $response->assertOk();
        collect($response->json('data'))->each(
            fn ($task) => $this->assertEquals('high', $task['priority'])
        );
    }

    public function test_filtering_by_an_invalid_status_value_is_rejected(): void
    {
        $this->authenticateAs($this->manager);

        $this->getJson('/api/tasks?status=bogus')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    // --- show / update / destroy ---------------------------------------

    public function test_unrelated_user_cannot_view_a_task(): void
    {
        $this->authenticateAs($this->member);

        $otherTask = Task::factory()->create();

        $this->getJson("/api/tasks/{$otherTask->id}")->assertForbidden();
    }

    public function test_manager_can_update_their_own_task(): void
    {
        $this->authenticateAs($this->manager);

        $task = Task::factory()->createdBy($this->manager)->create(['title' => 'Old title']);

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'New title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'New title');
    }

    public function test_manager_cannot_update_another_managers_task(): void
    {
        $this->authenticateAs($this->manager);

        $otherTask = Task::factory()->create();

        $this->putJson("/api/tasks/{$otherTask->id}", ['title' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_team_member_cannot_use_the_general_update_endpoint(): void
    {
        $this->authenticateAs($this->member);

        $task = Task::factory()->assignedTo($this->member)->create();

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Nope'])
            ->assertForbidden();
    }

    public function test_manager_can_delete_their_own_task(): void
    {
        $this->authenticateAs($this->manager);

        $task = Task::factory()->createdBy($this->manager)->create();

        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_team_member_cannot_delete_a_task(): void
    {
        $this->authenticateAs($this->member);

        $task = Task::factory()->assignedTo($this->member)->create();

        $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/tasks')->assertUnauthorized();
    }

    public function test_unauthenticated_requests_are_rejected_even_without_an_explicit_json_accept_header(): void
    {
        // Regression guard: this is a pure JSON API with no 'login' route.
        // Laravel's default guest-redirect behaviour tries to build one and
        // throws a RouteNotFoundException (-> 500) for a client that
        // doesn't explicitly send "Accept: application/json" — see the
        // redirectGuestsTo() override in bootstrap/app.php.
        $this->get('/api/tasks')->assertUnauthorized();
    }
}
