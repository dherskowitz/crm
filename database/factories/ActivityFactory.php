<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'user_id' => User::factory(),
            'type' => ActivityType::Note,
            'body' => ['text' => fake()->sentence()],
            'is_pinned' => false,
            'occurred_at' => now(),
        ];
    }

    public function type(ActivityType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }

    public function system(): static
    {
        return $this->state(fn () => ['type' => ActivityType::System, 'user_id' => null]);
    }
}
