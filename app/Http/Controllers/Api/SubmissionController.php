<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubmissionResource;
use App\Models\Submission;
use App\Services\Dashboard\EditorialQueries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubmissionController extends Controller
{
    public function index(Request $request, EditorialQueries $queries): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', 'integer', 'min:0'],
            'context_id' => ['nullable', 'integer', 'min:0'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('dashboard.max_per_page')],
        ]);

        $submissions = $queries
            ->submissions(
                isset($filters['status']) ? (int) $filters['status'] : null,
                isset($filters['context_id']) ? (int) $filters['context_id'] : null,
            )
            ->paginate((int) ($filters['per_page'] ?? config('dashboard.per_page')))
            ->withQueryString();

        return SubmissionResource::collection($submissions);
    }

    public function show(Submission $submission): SubmissionResource
    {
        return new SubmissionResource(
            $submission->load(['reviewAssignments' => fn ($query) => $query->orderBy('id')]),
        );
    }
}
