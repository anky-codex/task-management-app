<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a small, ready-to-poke-at dataset for manual/Postman testing:
     * one manager, two team members, and a handful of tasks across
     * different statuses (including an overdue one).
     */
    public function run(): void
    {
        $manager = User::factory()->manager()->create([
            'name' => 'Morgan Manager',
            'email' => 'manager@example.com',
        ]);

        $alice = User::factory()->member()->create([
            'name' => 'Alice Member',
            'email' => 'alice@example.com',
        ]);

        $bob = User::factory()->member()->create([
            'name' => 'Bob Member',
            'email' => 'bob@example.com',
        ]);

        Task::factory()->createdBy($manager)->assignedTo($alice)->create([
            'title' => 'Set up CI pipeline',
            'status' => Task::STATUS_IN_PROGRESS,
            'priority' => Task::PRIORITY_HIGH,
            'due_date' => now()->addDays(3),
        ]);

        Task::factory()->createdBy($manager)->assignedTo($alice)->overdue()->create([
            'title' => 'Fix login redirect bug',
            'priority' => Task::PRIORITY_HIGH,
        ]);

        Task::factory()->createdBy($manager)->assignedTo($bob)->create([
            'title' => 'Write onboarding docs',
            'status' => Task::STATUS_UNDER_QA,
            'priority' => Task::PRIORITY_MEDIUM,
            'due_date' => now()->addWeek(),
        ]);

        Task::factory()->createdBy($manager)->assignedTo($bob)->withStatus(Task::STATUS_SUBMITTED)->create([
            'title' => 'Upgrade PHP to 8.3',
            'priority' => Task::PRIORITY_LOW,
        ]);
    }
}
