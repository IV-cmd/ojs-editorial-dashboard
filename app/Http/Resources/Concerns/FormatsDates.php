<?php

namespace App\Http\Resources\Concerns;

use DateTimeInterface;

trait FormatsDates
{
    protected static function isoDate(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : null;
    }
}
