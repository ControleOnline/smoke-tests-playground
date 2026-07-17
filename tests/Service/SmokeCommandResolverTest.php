<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Service;

use ControleOnline\SmokeTestsPlayground\Service\SmokeCommandResolver;
use PHPUnit\Framework\TestCase;

final class SmokeCommandResolverTest extends TestCase
{
    public function testPlaywrightExecutableIsResolvedForTheCurrentPlatform(): void
    {
        $resolver = new SmokeCommandResolver();
        $arguments = $resolver->toProcessArguments('node_modules/.bin/playwright test --config=playwright.config.cjs tests/browser/company-advertiser-route-smoke.spec.js');

        self::assertSame('node_modules/.bin/playwright', $arguments[0]);

        self::assertSame('test', $arguments[1]);
        self::assertSame('--config=playwright.config.cjs', $arguments[2]);
        self::assertSame('tests/browser/company-advertiser-route-smoke.spec.js', $arguments[3]);
    }

    public function testNodeCommandsResolveTheCurrentNodeBinary(): void
    {
        $resolver = new SmokeCommandResolver();
        $arguments = $resolver->toProcessArguments('node node_modules/@playwright/test/cli.js test --config=playwright.config.cjs tests/browser/company-advertiser-route-smoke.spec.js');

        self::assertMatchesRegularExpression('/^node(?:\.exe)?$/i', basename($arguments[0]));
        self::assertSame('node_modules/@playwright/test/cli.js', $arguments[1]);
        self::assertSame('test', $arguments[2]);
        self::assertSame('--config=playwright.config.cjs', $arguments[3]);
        self::assertSame('tests/browser/company-advertiser-route-smoke.spec.js', $arguments[4]);
    }
}
