<?php

namespace App\Console\Commands;

use App\Jobs\SyncSubmissions;
use Illuminate\Console\Command;

class SyncOjsCommand extends Command
{
    protected $signature = 'ojs:sync';

    protected $description = 'Ingest OJS submissions and review assignments into the local database.';

    public function handle(): int
    {
        SyncSubmissions::dispatch();

        $this->info('OJS sync dispatched.');

        return self::SUCCESS;
    }
}
