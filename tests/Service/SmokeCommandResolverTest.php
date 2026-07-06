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
        $arguments = $resolver->toProcessArguments('node_modules/.bin/playwright test --config=playwright.config.cjs tests/browser/transporter-login.spec.js');

        self::assertSame('node_modules/.bin/playwright', $arguments[0]);

        self::assertSame('test', $arguments[1]);
        self::assertSame('--config=playwright.config.cjs', $arguments[2]);
        self::assertSame('tests/browser/transporter-login.spec.js', $arguments[3]);
    }
}
