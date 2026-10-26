<?php

namespace Database\Factories;

use App\Models\ReviewAssignment;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewAssignment>
 */
class ReviewAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'ojs_id' => $this->faker->unique()->numberBetween(1000000, 2000000000),
            'reviewer_id' => $this->faker->numberBetween(100000, 999999999),
            'round' => $this->faker->numberBetween(1, 3),
            'review_round_id' => $this->faker->numberBetween(1000000, 2000000000),
            'status' => $this->faker->randomElement([4, 5, 6]),
            'date_assigned' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
            'date_due' => $this->faker->dateTimeBetween('now', '+3 months'),
            'date_response_due' => $this->faker->dateTimeBetween('-1 month', '+3 months'),
            'date_confirmed' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'date_completed' => null,
            'declined' => false,
            'cancelled' => false,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'date_due' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
            'date_completed' => null,
            'declined' => false,
            'cancelled' => false,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'date_completed' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'declined' => true,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'cancelled' => true,
        ]);
    }
}
