<?php

namespace App\Services\Ingestion;

use RuntimeException;

/** An OJS payload is missing a field the local schema needs to key the row. */
final class InvalidOjsPayloadException extends RuntimeException {}
