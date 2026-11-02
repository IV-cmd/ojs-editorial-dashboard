<?php

namespace App\Events;

use App\Models\SyncRun;
use Illuminate\Foundation\Events\Dispatchable;

/** The SyncRun carries status, counts, and (once finished) the duration. */
class SyncRunFinished
{
    use Dispatchable;

    public function __construct(public readonly SyncRun $run) {}
}
