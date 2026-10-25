<?php

use App\Services\Ojs\HttpOjsClient;
use App\Services\Ojs\OjsApiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends bearer auth and accumulates paginated submission summaries', function () {
    Http::fakeSequence()
        ->push([
            'itemsMax' => 3,
            'items' => [
                ['id' => 1],
                ['id' => 2],
            ],
        ])
        ->push([
            'itemsMax' => 3,
            'items' => [
                ['id' => 3],
            ],
        ]);

    $client = new HttpOjsClient('https://ojs.example.test/index.php/journal/api/v1', 'test-token', 10);
    $submissions = $client->getSubmissions();

    expect($submissions)->toHaveCount(3)
        ->and($submissions[2]['id'])->toBe(3);

    Http::assertSentCount(2);

    $assertPage = function (Request $request, string $offset): bool {
        $queryString = parse_url($request->url(), PHP_URL_QUERY);
        parse_str(is_string($queryString) ? $queryString : '', $query);

        return $request->hasHeader('Authorization', 'Bearer test-token')
            && ($query['count'] ?? null) === '100'
            && ($query['offset'] ?? null) === $offset;
    };

    Http::assertSent(fn (Request $request): bool => $assertPage($request, '0'));
    Http::assertSent(fn (Request $request): bool => $assertPage($request, '2'));
});

it('returns a decoded submission detail object', function () {
    Http::fakeSequence()->push([
        'id' => 105,
        'reviewAssignments' => [
            ['id' => 501, 'status' => 6],
        ],
    ]);

    $client = new HttpOjsClient('https://ojs.example.test/index.php/journal/api/v1', 'test-token', 10);
    $submission = $client->getSubmission(105);

    expect($submission['reviewAssignments'][0]['status'])->toBe(6);
});

it('wraps failed responses with status and a redacted truncated body snippet', function () {
    $responseBody = str_repeat('x', 250).'test-token'.str_repeat('y', 300);
    Http::fakeSequence()->push($responseBody, 500);

    $client = new HttpOjsClient('https://ojs.example.test/index.php/journal/api/v1', 'test-token', 10);
    $exception = null;

    try {
        $client->getSubmission(105);
    } catch (OjsApiException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(OjsApiException::class)
        ->and($exception?->statusCode)->toBe(500)
        ->and($exception?->responseBodySnippet)->toHaveLength(500)
        ->and($exception?->getMessage())->not->toContain('test-token');
});
