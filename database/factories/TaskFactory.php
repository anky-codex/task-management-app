<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'created_by' => User::factory()->manager(),
            'user_id' => User::factory()->member(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'status' => Task::STATUS_TODO,
            'priority' => $this->faker->randomElement(Task::PRIORITIES),
            'due_date' => $this->faker->optional()->dateTimeBetween('-10 days', '+10 days'),
            'submitted_at' => null,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => now()->subDays(3),
            'status' => Task::STATUS_TODO,
        ]);
    }

    public function withStatus(string $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'submitted_at' => $status === Task::STATUS_SUBMITTED ? now() : null,
        ]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => ['created_by' => $user->id]);
    }
}
