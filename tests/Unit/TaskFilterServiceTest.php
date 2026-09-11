<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskFilterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TaskFilterService $service;

    protected User $manager;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TaskFilterService;
        $this->manager = User::factory()->manager()->create();
        $this->member = User::factory()->member()->create();
    }

    protected function baseQuery()
    {
        return Task::query()->createdBy($this->manager->id);
    }

    public function test_it_sorts_by_priority_in_low_to_high_order(): void
    {
        Task::factory()->createdBy($this->manager)->create(['priority' => 'high', 'title' => 'A']);
        Task::factory()->createdBy($this->manager)->create(['priority' => 'low', 'title' => 'B']);
        Task::factory()->createdBy($this->manager)->create(['priority' => 'medium', 'title' => 'C']);

        $results = $this->service->filter($this->baseQuery(), ['sort' => 'priority']);

        $this->assertEquals(['B', 'C', 'A'], $results->pluck('title')->toArray());
    }

    public function test_it_sorts_by_priority_descending_with_minus_prefix(): void
    {
        Task::factory()->createdBy($this->manager)->create(['priority' => 'low', 'title' => 'B']);
        Task::factory()->createdBy($this->manager)->create(['priority' => 'high', 'title' => 'A']);

        $results = $this->service->filter($this->baseQuery(), ['sort' => '-priority']);

        $this->assertEquals(['A', 'B'], $results->pluck('title')->toArray());
    }

    public function test_it_filters_by_status(): void
    {
        Task::factory()->createdBy($this->manager)->create(['status' => 'todo']);
        Task::factory()->createdBy($this->manager)->create(['status' => 'under_qa']);

        $results = $this->service->filter($this->baseQuery(), ['status' => 'under_qa']);

        $this->assertCount(1, $results);
        $this->assertEquals('under_qa', $results->first()->status);
    }

    public function test_overdue_excludes_submitted_tasks_even_with_a_past_due_date(): void
    {
        Task::factory()->createdBy($this->manager)->withStatus('submitted')->create([
            'due_date' => now()->subWeek(),
        ]);
        $stillOpen = Task::factory()->createdBy($this->manager)->create([
            'status' => 'todo',
            'due_date' => now()->subWeek(),
        ]);

        $results = $this->service->filter($this->baseQuery(), ['status' => 'overdue']);

        $this->assertCount(1, $results);
        $this->assertEquals($stillOpen->id, $results->first()->id);
    }

    public function test_it_filters_by_assigned_to(): void
    {
        $other = User::factory()->member()->create();

        Task::factory()->createdBy($this->manager)->assignedTo($this->member)->create();
        Task::factory()->createdBy($this->manager)->assignedTo($other)->create();

        $results = $this->service->filter($this->baseQuery(), ['assigned_to' => $this->member->id]);

        $this->assertCount(1, $results);
        $this->assertEquals($this->member->id, $results->first()->user_id);
    }

    public function test_it_respects_per_page(): void
    {
        Task::factory()->count(5)->createdBy($this->manager)->create();

        $results = $this->service->filter($this->baseQuery(), ['per_page' => 2]);

        $this->assertCount(2, $results->items());
        $this->assertEquals(5, $results->total());
    }

    public function test_default_sort_is_newest_first(): void
    {
        $older = Task::factory()->createdBy($this->manager)->create(['created_at' => now()->subDay()]);
        $newer = Task::factory()->createdBy($this->manager)->create(['created_at' => now()]);

        $results = $this->service->filter($this->baseQuery(), []);

        $this->assertEquals([$newer->id, $older->id], $results->pluck('id')->toArray());
    }
}
