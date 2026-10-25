<?php

namespace App\Providers;

use App\Services\Ojs\HttpOjsClient;
use App\Services\Ojs\MockOjsClient;
use App\Services\Ojs\OjsClientInterface;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OjsClientInterface::class, function (): OjsClientInterface {
            return match (config('ojs.driver')) {
                'http' => new HttpOjsClient(
                    (string) config('ojs.base_url', ''),
                    (string) config('ojs.api_token', ''),
                    (int) config('ojs.timeout', 10),
                ),
                'mock' => new MockOjsClient(
                    (string) config('ojs.fixture_path', base_path('database/fixtures/ojs')),
                ),
                default => throw new InvalidArgumentException('Unsupported OJS client driver.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
