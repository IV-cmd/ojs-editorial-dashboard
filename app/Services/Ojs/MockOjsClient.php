<?php

namespace App\Services\Ojs;

use JsonException;

final class MockOjsClient implements OjsClientInterface
{
    public function __construct(private readonly string $fixturePath) {}

    public function getSubmissions(): array
    {
        $submissions = $this->readFixture($this->fixturePath.'/submissions.json');

        if (! is_array($submissions) || ! array_is_list($submissions)) {
            throw new OjsApiException(500, 'The OJS submissions fixture must contain a JSON list.');
        }

        foreach ($submissions as $submission) {
            if (! is_array($submission) || array_is_list($submission)) {
                throw new OjsApiException(500, 'The OJS submissions fixture contains an invalid summary.');
            }
        }

        /** @var list<array<string, mixed>> $submissions */
        return $submissions;
    }

    public function getSubmission(int $id): array
    {
        $path = $this->fixturePath."/submissions/{$id}.json";

        if (! is_file($path)) {
            throw new OjsApiException(404, "No mock OJS submission fixture exists for ID {$id}.");
        }

        $submission = $this->readFixture($path);

        if (! is_array($submission) || $submission === [] || array_is_list($submission)) {
            throw new OjsApiException(500, 'The OJS submission fixture must contain a JSON object.');
        }

        /** @var array<string, mixed> $submission */
        return $submission;
    }

    private function readFixture(string $path): mixed
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new OjsApiException(500, 'An OJS fixture could not be read.');
        }

        try {
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new OjsApiException(500, 'An OJS fixture contains invalid JSON.');
        }
    }
}
