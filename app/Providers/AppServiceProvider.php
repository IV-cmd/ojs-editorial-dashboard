<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Dashboard\EditorialQueries;
use App\Services\Ojs\HttpOjsClient;
use App\Services\Ojs\MockOjsClient;
use App\Services\Ojs\OjsClientInterface;
use App\Services\Ojs\OjsDriverGuard;
use Illuminate\Support\Facades\Gate;
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

        $this->app->bind(EditorialQueries::class, fn (): EditorialQueries => new EditorialQueries(
            (int) config('dashboard.stuck_days', 14),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        OjsDriverGuard::assertNotMockInProduction(
            $this->app->environment(),
            (string) config('ojs.driver'),
        );

        // Reviewer identities are sensitive in peer review, so only editors may list raw
        // submission records or see which reviewer holds an assignment.
        Gate::define('view-submissions', fn (User $user): bool => $user->isEditor());
        Gate::define('view-reviewer-identity', fn (User $user): bool => $user->isEditor());
    }
}
