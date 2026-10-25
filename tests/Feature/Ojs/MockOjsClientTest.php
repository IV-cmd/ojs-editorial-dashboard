<?php

use App\Services\Ojs\MockOjsClient;
use App\Services\Ojs\OjsApiException;
use App\Services\Ojs\OjsClientInterface;

it('resolves the mock client and returns summary fixtures without review assignments', function () {
    $client = app(OjsClientInterface::class);

    expect($client)->toBeInstanceOf(MockOjsClient::class);

    $submissions = $client->getSubmissions();

    expect($submissions)->toHaveCount(5)
        ->and($submissions[0]['title'])->toBe('Synthetic submission: stalled at intake')
        ->and($submissions[0])->not->toHaveKey('reviewAssignments');
});

it('returns review assignments from submission detail fixtures', function () {
    $submission = app(OjsClientInterface::class)->getSubmission(105);

    expect($submission['reviewAssignments'])->toHaveCount(3)
        ->and($submission['reviewAssignments'][0]['status'])->toBe(6)
        ->and($submission['reviewAssignments'][1]['status'])->toBe(4)
        ->and($submission['reviewAssignments'][2]['status'])->toBe(5);
});

it('throws a typed 404 exception when a detail fixture is missing', function () {
    $exception = null;

    try {
        app(OjsClientInterface::class)->getSubmission(999);
    } catch (OjsApiException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(OjsApiException::class)
        ->and($exception?->statusCode)->toBe(404);
});
