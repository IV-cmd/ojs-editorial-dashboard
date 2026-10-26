<?php

namespace Database\Factories;

use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'context_id' => 1,
            'ojs_id' => $this->faker->unique()->numberBetween(100000, 999999999),
            'status' => $this->faker->randomElement([1, 3, 4, 5]),
            'stage_id' => $this->faker->randomElement([1, 2, 3, 4, 5]),
            'submission_progress' => '',
            'current_publication_id' => null,
            'date_submitted' => $this->faker->dateTimeBetween('-2 years', '-2 months'),
            'date_last_activity' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'last_modified' => $this->faker->dateTimeBetween('-2 months', 'now'),
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 1,
            'stage_id' => 1,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 3,
            'stage_id' => 5,
            'current_publication_id' => $this->faker->numberBetween(100000, 999999999),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 4,
            'stage_id' => 1,
        ]);
    }
}
