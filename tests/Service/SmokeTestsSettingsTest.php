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
            './node_modules/.bin/playwright test --config=playwright.config.cjs tests/browser/transporter-login.spec.js',
            $settings->runCommand(),
        );
    }
}
