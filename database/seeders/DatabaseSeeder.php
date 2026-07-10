<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Canonical test accounts (one per role) plus extra teammates so the
        // queue, reassignment, and leaderboard have a real team behind them.
        // Everyone logs in with the password "password".
        $this->seedUser('Admin User', 'admin@example.com', Role::Admin);
        $this->seedUser('Morgan Reyes', 'manager@example.com', Role::Manager);
        $this->seedUser('Priya Nair', 'priya@example.com', Role::Manager);
        $this->seedUser('Member User', 'member@example.com', Role::Member);
        $this->seedUser('Alex Chen', 'alex@example.com', Role::Member);
        $this->seedUser('Jordan Smith', 'jordan@example.com', Role::Member);
        $this->seedUser('Taylor Brooks', 'taylor@example.com', Role::Member);

        $this->call([
            TagSeeder::class,
            DemoDataSeeder::class,
        ]);
    }

    /**
     * Create (or update) an active, verified user. Idempotent so the seeder is
     * safe to re-run without `migrate:fresh`.
     */
    private function seedUser(string $name, string $email, Role $role): void
    {
        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'role' => $role,
                'password' => 'password',
                'email_verified_at' => now(),
                'invitation_accepted_at' => now(),
            ],
        );
    }
}
