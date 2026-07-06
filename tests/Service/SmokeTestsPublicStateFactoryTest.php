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
        self::assertSame(['status', 'progress', 'message', 'lastRunAt', 'summary', 'tests'], array_keys($state));
        self::assertSame(['total' => 0, 'passed' => 0, 'failed' => 0], $state['summary']);
        self::assertSame([], $state['tests']);
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
        self::assertArrayHasKey('summary', $state);
        self::assertArrayHasKey('tests', $state);
        self::assertArrayNotHasKey('testsPath', $state);
        self::assertArrayNotHasKey('reportPath', $state);
        self::assertArrayNotHasKey('report', $state);
    }

    public function testCreateIncludesSanitizedTestsFromTheReport(): void
    {
        $projectDir = $this->makeProjectDir();
        $this->writeReport($projectDir, [
            'generatedAt' => '2026-07-06T12:00:00+00:00',
            'suite' => 'smoke-tests-playground',
            'tests' => [
                [
                    'title' => 'fluxo da nova viagem',
                    'status' => 'failed',
                    'error' => 'Expect failure',
                    'screenshots' => [
                        [
                            'label' => 'Tela de login',
                            'path' => '01-login-screen.png',
                        ],
                    ],
                    'steps' => [
                        [
                            'title' => 'abre o staging e autentica',
                            'status' => 'passed',
                            'screenshots' => [
                                [
                                    'label' => 'Login aberto',
                                    'path' => 'steps/01-login-open.png',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        file_put_contents($projectDir.'/var/tests/browser-smoke/company-advertiser-route/01-login-screen.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2P5foAAAAASUVORK5CYII='));
        mkdir($projectDir.'/var/tests/browser-smoke/company-advertiser-route/steps', 0777, true);
        file_put_contents($projectDir.'/var/tests/browser-smoke/company-advertiser-route/steps/01-login-open.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2P5foAAAAASUVORK5CYII='));

        $factory = $this->makeFactory($projectDir);

        $state = $factory->create();

        self::assertSame('failed', $state['status']);
        self::assertSame(['total' => 1, 'passed' => 0, 'failed' => 1], $state['summary']);
        self::assertCount(1, $state['tests']);
        self::assertSame('fluxo da nova viagem', $state['tests'][0]['name']);
        self::assertSame('failed', $state['tests'][0]['status']);
        self::assertSame('Expect failure', $state['tests'][0]['error']);
        self::assertSame('Tela de login', $state['tests'][0]['screenshots'][0]['label']);
        self::assertStringStartsWith('data:image/png;base64,', $state['tests'][0]['screenshots'][0]['src']);
        self::assertArrayNotHasKey('path', $state['tests'][0]['screenshots'][0]);
        self::assertSame('abre o staging e autentica', $state['tests'][0]['steps'][0]['name']);
        self::assertSame('passed', $state['tests'][0]['steps'][0]['status']);
        self::assertSame('Login aberto', $state['tests'][0]['steps'][0]['screenshots'][0]['label']);
    }

    public function testRunResponseKeepsTheFullFailureOutput(): void
    {
        $factory = $this->makeFactory($this->makeProjectDir());

        $state = $factory->createRunResponse(
            new SmokeRunResult(
                false,
                1,
                "[chromium] › tests/browser/company-advertiser-route-smoke.spec.js:168:3 › browser smoke - company advertiser route › abre o staging, cria a viagem e valida a listagem com prints em /tests\n",
                "Error: Playwright Test did not expect test.describe() to be called here.\n",
            ),
            'POST',
            '2026-07-06T12:00:00+00:00',
        );

        self::assertSame('failed', $state['status']);
        self::assertStringContainsString('[chromium] › tests/browser/company-advertiser-route-smoke.spec.js', $state['message']);
        self::assertStringContainsString('Error: Playwright Test did not expect test.describe() to be called here.', $state['message']);
        self::assertStringContainsString("--- stdout ---", $state['message']);
    }

    private function makeFactory(string $projectDir): SmokeTestsPublicStateFactory
    {
        $this->resetEnv();
        $_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH'] = $projectDir.'/var/tests/browser-smoke/company-advertiser-route';
        putenv('SMOKE_TESTS_PLAYGROUND_TESTS_PATH='.$_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH']);

        $settings = new SmokeTestsSettings($projectDir);

        return new SmokeTestsPublicStateFactory(
            new SmokeReportReader($settings),
        );
    }

    private function makeProjectDir(): string
    {
        $projectDir = sys_get_temp_dir().'/smoke-tests-playground-'.bin2hex(random_bytes(6));
        mkdir($projectDir.'/var/tests/browser-smoke/company-advertiser-route', 0777, true);

        return $projectDir;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function writeReport(string $projectDir, array $report): void
    {
        file_put_contents(
            $projectDir.'/var/tests/browser-smoke/company-advertiser-route/report.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    private function resetEnv(): void
    {
        unset($_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH']);
        putenv('SMOKE_TESTS_PLAYGROUND_TESTS_PATH');
    }
}
