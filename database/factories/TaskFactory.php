<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'owner_id' => User::factory(),
            'title' => fake()->sentence(4),
            'due_at' => now()->addDays(fake()->numberBetween(1, 14)),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['due_at' => now()->subDays(2), 'completed_at' => null]);
    }
}
