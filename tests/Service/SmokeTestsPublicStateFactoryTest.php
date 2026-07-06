<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Service;

use ControleOnline\SmokeTestsPlayground\Service\SmokeReportReader;
use ControleOnline\SmokeTestsPlayground\Service\SmokeRunResult;
use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsPublicStateFactory;
use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsSettings;
use PHPUnit\Framework\TestCase;

final class SmokeTestsPublicStateFactoryTest extends TestCase
{
    public function testCreateReturnsIdleStateWhenReportIsMissing(): void
    {
        $factory = $this->makeFactory($this->makeProjectDir());

        $state = $factory->create();

        self::assertSame('idle', $state['status']);
        self::assertSame(0, $state['progress']);
        self::assertSame('Ainda não existe um relatório publicado.', $state['message']);
        self::assertNull($state['lastRunAt']);
        self::assertSame(['status', 'progress', 'message', 'lastRunAt'], array_keys($state));
    }

    public function testCreateReturnsPublicRunResponseOnly(): void
    {
        $factory = $this->makeFactory($this->makeProjectDir());

        $state = $factory->createRunResponse(
            new SmokeRunResult(true, 0, 'ok', ''),
            'POST',
            '2026-07-06T12:00:00+00:00',
        );

        self::assertSame('passed', $state['status']);
        self::assertSame(100, $state['progress']);
        self::assertSame('Execução concluída com sucesso.', $state['message']);
        self::assertSame('2026-07-06T12:00:00+00:00', $state['run']['requestedAt']);
        self::assertSame('POST', $state['run']['requestedMethod']);
        self::assertArrayNotHasKey('testsPath', $state);
        self::assertArrayNotHasKey('reportPath', $state);
        self::assertArrayNotHasKey('report', $state);
    }

    private function makeFactory(string $projectDir): SmokeTestsPublicStateFactory
    {
        $this->resetEnv();
        $_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH'] = $projectDir.'/var/tests/browser-smoke/transporter-login';
        putenv('SMOKE_TESTS_PLAYGROUND_TESTS_PATH='.$_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH']);

        $settings = new SmokeTestsSettings($projectDir);

        return new SmokeTestsPublicStateFactory(
            new SmokeReportReader($settings),
        );
    }

    private function makeProjectDir(): string
    {
        $projectDir = sys_get_temp_dir().'/smoke-tests-playground-'.bin2hex(random_bytes(6));
        mkdir($projectDir.'/var/tests/browser-smoke/transporter-login', 0777, true);

        return $projectDir;
    }

    private function resetEnv(): void
    {
        unset($_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH']);
        putenv('SMOKE_TESTS_PLAYGROUND_TESTS_PATH');
    }
}
