<?php

use App\Services\Ingestion\FailureReason;
use App\Services\Ingestion\InvalidOjsPayloadException;
use App\Services\Ojs\OjsApiException;
use Illuminate\Database\QueryException;

it('buckets failures into a fixed set of reasons', function (Throwable $e, string $reason) {
    expect(FailureReason::classify($e))->toBe($reason);
})->with([
    'transport' => [new OjsApiException(503), FailureReason::API_ERROR],
    'payload' => [new InvalidOjsPayloadException('missing id'), FailureReason::VALIDATION],
    'database' => [new QueryException('mysql', 'insert', [], new PDOException('boom')), FailureReason::DB],
    'anything else' => [new RuntimeException('?'), FailureReason::OTHER],
]);
