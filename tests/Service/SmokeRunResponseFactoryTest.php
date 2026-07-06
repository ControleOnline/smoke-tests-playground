<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Service;

use ControleOnline\SmokeTestsPlayground\Service\SmokeRunResponseFactory;
use ControleOnline\SmokeTestsPlayground\Service\SmokeRunResult;
use PHPUnit\Framework\TestCase;

final class SmokeRunResponseFactoryTest extends TestCase
{
    public function testCreateReturnsPublicRunResponse(): void
    {
        $factory = new SmokeRunResponseFactory();

        $payload = $factory->create(
            new SmokeRunResult(true, 0, 'runner-ok', ''),
            'POST',
            '2026-07-06T12:00:00+00:00',
        );

        self::assertSame('passed', $payload['status']);
        self::assertSame(100, $payload['progress']);
        self::assertSame('Execução concluída com sucesso.', $payload['message']);
        self::assertSame('2026-07-06T12:00:00+00:00', $payload['requestedAt']);
        self::assertSame('POST', $payload['requestedMethod']);
        self::assertSame(0, $payload['exitCode']);
    }

    public function testCreateKeepsTheFullFailureOutput(): void
    {
        $factory = new SmokeRunResponseFactory();

        $payload = $factory->create(
            new SmokeRunResult(
                false,
                1,
                "Running 1 test using 1 worker\n",
                "Error: browserType.launch: Executable doesn't exist.\n",
            ),
            'POST',
            '2026-07-06T12:00:00+00:00',
        );

        self::assertSame('failed', $payload['status']);
        self::assertStringContainsString("Running 1 test using 1 worker", $payload['message']);
        self::assertStringContainsString("Error: browserType.launch: Executable doesn't exist.", $payload['message']);
        self::assertStringContainsString("--- stdout ---", $payload['message']);
    }
}
