<?php

use App\Services\Ojs\OjsConfigurationException;
use App\Services\Ojs\OjsDriverGuard;

it('refuses the mock driver in production', function () {
    expect(fn () => OjsDriverGuard::assertNotMockInProduction('production', 'mock'))
        ->toThrow(
            OjsConfigurationException::class,
            'OJS driver is set to "mock" in production; refusing to boot and serve fabricated data. Set OJS_DRIVER=http.'
        );
});

it('allows the HTTP driver in production', function () {
    expect(OjsDriverGuard::assertNotMockInProduction('production', 'http'))->toBeNull();
});

it('allows the mock driver locally', function () {
    expect(OjsDriverGuard::assertNotMockInProduction('local', 'mock'))->toBeNull();
});

it('allows the mock driver in testing', function () {
    expect(OjsDriverGuard::assertNotMockInProduction('testing', 'mock'))->toBeNull();
});
