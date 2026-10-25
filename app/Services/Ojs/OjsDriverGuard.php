<?php

namespace App\Services\Ojs;

final class OjsDriverGuard
{
    public static function assertNotMockInProduction(string $environment, string $driver): void
    {
        if ($environment === 'production' && $driver === 'mock') {
            throw new OjsConfigurationException(
                'OJS driver is set to "mock" in production; refusing to boot and serve fabricated data. Set OJS_DRIVER=http.'
            );
        }
    }
}
