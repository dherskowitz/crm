<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        $filename = fake()->word().'.pdf';

        return [
            'attachable_type' => (new Lead)->getMorphClass(),
            'attachable_id' => Lead::factory(),
            'disk' => 'local',
            'path' => 'attachments/'.fake()->uuid().'/'.$filename,
            'filename' => $filename,
            'size' => fake()->numberBetween(1000, 5_000_000),
            'mime' => 'application/pdf',
            'uploaded_by' => null,
        ];
    }
}
