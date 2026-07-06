<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Service;

use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsSettings;
use PHPUnit\Framework\TestCase;

final class SmokeTestsSettingsTest extends TestCase
{
    public function testDefaultRunCommandUsesLocalPlaywrightBinary(): void
    {
        $settings = new SmokeTestsSettings('/app');

        self::assertSame(
            'node node_modules/@playwright/test/cli.js test --config=playwright.config.cjs tests/browser/*.spec.js',
            $settings->runCommand(),
        );
    }

    public function testDefaultEnvLinesIncludeLocalBrowserPath(): void
    {
        $settings = new SmokeTestsSettings('/app');

        self::assertContains('PLAYWRIGHT_BROWSERS_PATH="0"', $settings->defaultEnvLines());
        self::assertContains('SMOKE_TESTS_PLAYGROUND_TESTS_PATH="var/tests/browser-smoke"', $settings->defaultEnvLines());
    }
}
