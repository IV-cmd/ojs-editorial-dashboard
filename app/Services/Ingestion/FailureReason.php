<?php

namespace App\Services\Ingestion;

use App\Services\Ojs\OjsApiException;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Buckets an ingestion failure into a small fixed set of reasons, so failures can be
 * counted per reason without an unbounded number of metric label values.
 */
final class FailureReason
{
    public const API_ERROR = 'api_error';

    public const VALIDATION = 'validation';

    public const DB = 'db';

    public const OTHER = 'other';

    public const ALL = [self::API_ERROR, self::VALIDATION, self::DB, self::OTHER];

    public static function classify(Throwable $e): string
    {
        return match (true) {
            $e instanceof OjsApiException => self::API_ERROR,
            $e instanceof InvalidOjsPayloadException => self::VALIDATION,
            $e instanceof QueryException => self::DB,
            default => self::OTHER,
        };
    }
}
