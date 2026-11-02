<?php

use App\Services\Ojs\MockOjsClient;
use App\Services\Ojs\OjsApiException;
use App\Services\Ojs\OjsClientInterface;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/** Mock-backed client that fails the listing, or one submission's detail fetch. */
function failingOjsClient(?int $failDetailId = null, bool $failListing = false): OjsClientInterface
{
    return new class(new MockOjsClient(base_path('database/fixtures/ojs')), $failDetailId, $failListing) implements OjsClientInterface
    {
        public function __construct(private MockOjsClient $inner, private ?int $failDetailId, private bool $failListing) {}

        public function getSubmissions(): array
        {
            if ($this->failListing) {
                throw new OjsApiException(503, 'upstream unavailable');
            }

            return $this->inner->getSubmissions();
        }

        public function getSubmission(int $id): array
        {
            if ($id === $this->failDetailId) {
                throw new OjsApiException(500, 'detail exploded');
            }

            return $this->inner->getSubmission($id);
        }
    };
}
