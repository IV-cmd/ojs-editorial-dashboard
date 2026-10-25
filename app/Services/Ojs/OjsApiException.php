<?php

namespace App\Services\Ojs;

use RuntimeException;

final class OjsApiException extends RuntimeException
{
    public readonly string $responseBodySnippet;

    public function __construct(public readonly int $statusCode, string $responseBody = '')
    {
        $this->responseBodySnippet = mb_substr($responseBody, 0, 500);

        $message = $this->responseBodySnippet === ''
            ? "OJS API request failed with HTTP status {$statusCode}."
            : "OJS API request failed with HTTP status {$statusCode}: {$this->responseBodySnippet}";

        parent::__construct($message);
    }
}
