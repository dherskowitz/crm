<?php

namespace Database\Factories;

use App\Enums\LostReason;
use App\Enums\Source;
use App\Enums\Stage;
use App\Enums\WinReason;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'source' => fake()->randomElement(Source::cases()),
            'form_type' => null,
            'stage' => Stage::New,
            'value' => fake()->optional()->randomFloat(2, 500, 50000),
            'owner_id' => null,
            'last_activity_at' => now(),
        ];
    }

    public function forCompany(?Company $company = null): static
    {
        return $this->state(fn () => ['company_id' => ($company ?? Company::factory())]);
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn () => ['owner_id' => $user->id]);
    }

    public function unassigned(): static
    {
        return $this->state(fn () => ['owner_id' => null]);
    }

    public function stage(Stage $stage): static
    {
        return $this->state(fn () => ['stage' => $stage]);
    }

    public function won(): static
    {
        return $this->state(fn () => [
            'stage' => Stage::Won,
            'win_reason' => WinReason::BestFit,
            'converted_at' => now(),
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn () => [
            'stage' => Stage::Lost,
            'lost_reason' => LostReason::NoResponse,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()->subMonths(20)]);
    }
}
