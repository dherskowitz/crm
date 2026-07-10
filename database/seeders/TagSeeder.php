<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * The predefined, non-editable tag set for v1.
     *
     * @var list<array{name: string, color: string}>
     */
    private const TAGS = [
        ['name' => 'Hot', 'color' => '#ef4444'],
        ['name' => 'Warm', 'color' => '#f59e0b'],
        ['name' => 'Cold', 'color' => '#3b82f6'],
        ['name' => 'Enterprise', 'color' => '#8b5cf6'],
        ['name' => 'SMB', 'color' => '#10b981'],
        ['name' => 'Referral', 'color' => '#ec4899'],
        ['name' => 'Newsletter', 'color' => '#64748b'],
    ];

    public function run(): void
    {
        foreach (self::TAGS as $tag) {
            Tag::updateOrCreate(['name' => $tag['name']], ['color' => $tag['color']]);
        }
    }
}
