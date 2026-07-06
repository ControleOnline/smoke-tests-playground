<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Service;

use ControleOnline\SmokeTestsPlayground\Service\SmokeReportReader;
use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsIndexFactory;
use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsSettings;
use PHPUnit\Framework\TestCase;

final class SmokeTestsIndexFactoryTest extends TestCase
{
    public function testCreateReturnsIdleStateWhenNoReportsExist(): void
    {
        $projectDir = $this->makeProjectDir();
        $factory = $this->makeFactory($projectDir);

        $index = $factory->create();

        self::assertSame('idle', $index['status']);
        self::assertSame(0, $index['progress']);
        self::assertSame('Nenhum relatório publicado ainda.', $index['message']);
        self::assertNull($index['lastRunAt']);
        self::assertSame(['self' => '/tests/index.json', 'artifacts' => '/tests/artifacts'], $index['links']);
        self::assertSame(['total' => 0, 'passed' => 0, 'failed' => 0], $index['summary']['suites']);
        self::assertSame(['total' => 0, 'passed' => 0, 'failed' => 0], $index['summary']['tests']);
        self::assertSame([], $index['suites']);
    }

    public function testCreateBuildsSuitesFromReportsAndNormalizesArtifacts(): void
    {
        $projectDir = $this->makeProjectDir();
        $this->writeReport($projectDir, 'transporter-login', [
            'generatedAt' => '2026-07-06T17:42:40.016Z',
            'suite' => 'transporter-login',
            'tests' => [
                [
                    'title' => 'faz login e chega em listwinner com prints em /tests',
                    'status' => 'passed',
                    'error' => null,
                    'screenshots' => [
                        [
                            'label' => 'Tela de login',
                            'path' => '01-login-screen.png',
                        ],
                    ],
                ],
            ],
        ]);
        $this->writePng($projectDir, 'transporter-login', '01-login-screen.png');

        $this->writeReport($projectDir, 'company-advertiser-route', [
            'generatedAt' => '2026-07-06T18:51:19.924Z',
            'suite' => 'company-advertiser-route',
            'tests' => [
                [
                    'title' => 'abre o staging, cria a viagem e valida a listagem com prints em /tests',
                    'status' => 'failed',
                    'error' => 'A página /company/advertiser/route/new retornou 504 Gateway Time-out no staging.',
                    'screenshots' => [],
                    'steps' => [
                        [
                            'title' => 'Abre o staging protegido e autentica a Maria',
                            'status' => 'passed',
                            'error' => null,
                            'screenshots' => [
                                [
                                    'label' => 'Tela de login',
                                    'path' => '01-login/login-screen.png',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->writePng($projectDir, 'company-advertiser-route', '01-login/login-screen.png');

        $factory = $this->makeFactory($projectDir);

        $index = $factory->create();

        self::assertSame('failed', $index['status']);
        self::assertSame(50, $index['progress']);
        self::assertSame(['total' => 2, 'passed' => 1, 'failed' => 1], $index['summary']['suites']);
        self::assertSame(['total' => 2, 'passed' => 1, 'failed' => 1], $index['summary']['tests']);
        self::assertCount(2, $index['suites']);
        self::assertSame('company-advertiser-route', $index['suites'][0]['suite']);
        self::assertSame('Company Advertiser Route', $index['suites'][0]['displayName']);
        self::assertSame('failed', $index['suites'][0]['status']);
        self::assertSame('transporter-login', $index['suites'][1]['suite']);
        self::assertSame('passed', $index['suites'][1]['status']);
        self::assertSame('Tela de login', $index['suites'][1]['tests'][0]['screenshots'][0]['label']);
        self::assertSame('/tests/artifacts/transporter-login/01-login-screen.png', $index['suites'][1]['tests'][0]['screenshots'][0]['url']);
        self::assertSame('image/png', $index['suites'][1]['tests'][0]['screenshots'][0]['mimeType']);
        self::assertSame('image', $index['suites'][1]['tests'][0]['screenshots'][0]['kind']);
        self::assertArrayNotHasKey('path', $index['suites'][1]['tests'][0]['screenshots'][0]);
        self::assertStringContainsString('falha', $index['message']);
        self::assertSame('2026-07-06T18:51:19.924Z', $index['lastRunAt']);
    }

    public function testCreateMarksInvalidJsonSuiteAsFailed(): void
    {
        $projectDir = $this->makeProjectDir();
        $suiteDir = $projectDir.'/var/tests/browser-smoke/invalid-suite';
        mkdir($suiteDir, 0777, true);
        file_put_contents($suiteDir.'/report.json', '{invalid json');

        $factory = $this->makeFactory($projectDir);

        $index = $factory->create();

        self::assertSame('failed', $index['status']);
        self::assertCount(1, $index['suites']);
        self::assertSame('invalid-suite', $index['suites'][0]['suite']);
        self::assertSame('failed', $index['suites'][0]['status']);
        self::assertSame(0, $index['suites'][0]['summary']['total']);
        self::assertNotEmpty($index['suites'][0]['error']);
    }

    private function makeFactory(string $projectDir): SmokeTestsIndexFactory
    {
        $this->resetEnv();
        $_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH'] = $projectDir.'/var/tests/browser-smoke';
        putenv('SMOKE_TESTS_PLAYGROUND_TESTS_PATH='.$_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH']);

        $settings = new SmokeTestsSettings($projectDir);

        return new SmokeTestsIndexFactory(
            new SmokeReportReader($settings),
        );
    }

    private function makeProjectDir(): string
    {
        $projectDir = sys_get_temp_dir().'/smoke-index-'.bin2hex(random_bytes(6));
        mkdir($projectDir.'/var/tests/browser-smoke', 0777, true);

        return $projectDir;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function writeReport(string $projectDir, string $suite, array $report): void
    {
        $suiteDir = $projectDir.'/var/tests/browser-smoke/'.$suite;
        if (!is_dir($suiteDir)) {
            mkdir($suiteDir, 0777, true);
        }

        file_put_contents(
            $suiteDir.'/report.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    private function writePng(string $projectDir, string $suite, string $relativePath): void
    {
        $suiteDir = $projectDir.'/var/tests/browser-smoke/'.$suite;
        $filePath = $suiteDir.'/'.$relativePath;
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        file_put_contents($filePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2P5foAAAAASUVORK5CYII='));
    }

    private function resetEnv(): void
    {
        unset($_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH']);
        putenv('SMOKE_TESTS_PLAYGROUND_TESTS_PATH');
    }
}
