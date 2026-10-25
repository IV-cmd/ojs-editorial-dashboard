<?php

namespace App\Services\Ojs;

// API behavior verified from pkp-lib/ojs source (version.xml 3.6.0.0); NOT live-tested.
interface OjsClientInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getSubmissions(): array;

    // In OJS 3.6, the submissions list returns summaries only; reviewAssignments appear only on this detail endpoint.
    /**
     * @return array<string, mixed>
     */
    public function getSubmission(int $id): array;
}
