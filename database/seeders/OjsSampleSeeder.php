<?php

namespace Database\Seeders;

use App\Models\ReviewAssignment;
use App\Models\Submission;
use Illuminate\Database\Seeder;

class OjsSampleSeeder extends Seeder
{
    public function run(): void
    {
        // Synthetic fixture records with deliberately synthetic OJS IDs; no live journal data is represented.
        $contextId = 9900001;

        Submission::factory()->queued()->create([
            'context_id' => $contextId,
            'ojs_id' => 990000001,
            'date_submitted' => now()->subYears(3),
            'date_last_activity' => now()->subYears(2),
            'last_modified' => now()->subYears(2),
        ]);

        Submission::factory()->queued()->create([
            'context_id' => $contextId,
            'ojs_id' => 990000002,
            'date_submitted' => now()->subDays(3),
            'date_last_activity' => now()->subDay(),
            'last_modified' => now()->subDay(),
        ]);

        Submission::factory()->published()->create([
            'context_id' => $contextId,
            'ojs_id' => 990000003,
            'current_publication_id' => 990001003,
            'date_submitted' => now()->subYear(),
            'date_last_activity' => now()->subMonths(6),
            'last_modified' => now()->subMonths(6),
        ]);

        Submission::factory()->declined()->create([
            'context_id' => $contextId,
            'ojs_id' => 990000004,
            'date_submitted' => now()->subMonths(8),
            'date_last_activity' => now()->subMonths(7),
            'last_modified' => now()->subMonths(7),
        ]);

        $inReview = Submission::factory()->queued()->create([
            'context_id' => $contextId,
            'ojs_id' => 990000005,
            'stage_id' => 3,
            'date_submitted' => now()->subMonths(3),
            'date_last_activity' => now()->subDays(10),
            'last_modified' => now()->subDays(10),
        ]);

        ReviewAssignment::factory()->for($inReview)->overdue()->create([
            'ojs_id' => 990001001,
            'status' => 6,
            'date_assigned' => now()->subDays(30),
            'date_due' => now()->subDays(5),
            'date_response_due' => now()->subDays(12),
            'date_confirmed' => now()->subDays(28),
        ]);

        ReviewAssignment::factory()->for($inReview)->create([
            'ojs_id' => 990001002,
            'status' => 5,
            'date_assigned' => now()->subDays(8),
            'date_due' => now()->addDays(14),
            'date_response_due' => now()->addDays(5),
            'date_confirmed' => now()->subDays(7),
        ]);

        ReviewAssignment::factory()->for($inReview)->completed()->create([
            'ojs_id' => 990001003,
            'status' => 4,
            'date_assigned' => now()->subDays(45),
            'date_due' => now()->subDays(15),
            'date_response_due' => now()->subDays(22),
            'date_confirmed' => now()->subDays(43),
            'date_completed' => now()->subDays(17),
        ]);
    }
}
