<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Service;

use ControleOnline\SmokeTestsPlayground\Service\SmokeReportReader;
use ControleOnline\SmokeTestsPlayground\Service\SmokeSuitePathCodec;
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
        self::assertSame(['self' => '/tests', 'artifacts' => '/tests/artifacts'], $index['links']);
        self::assertSame(['total' => 0, 'passed' => 0, 'failed' => 0], $index['summary']['types']);
        self::assertSame(['total' => 0, 'passed' => 0, 'failed' => 0], $index['summary']['suites']);
        self::assertSame(['total' => 0, 'passed' => 0, 'failed' => 0], $index['summary']['tests']);
        self::assertSame([], $index['types']);
        self::assertSame([], $index['suites']);
    }

    public function testCreateBuildsTypesFromJsonAndXmlReports(): void
    {
        $projectDir = $this->makeProjectDir();
        $codec = new SmokeSuitePathCodec();

        $this->writeJsonReport($projectDir, 'browser-smoke', 'transporter-login', [
            'generatedAt' => '2026-07-06T17:42:40.016Z',
            'suite' => 'transporter-login',
            'displayName' => 'Transporter Login',
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
        $this->writePng($projectDir, 'browser-smoke', 'transporter-login', '01-login-screen.png');

        $this->writeXmlReport($projectDir, 'phpunit', 'accounting', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="Unit" tests="2" failures="1" errors="0" skipped="0" time="0.012" timestamp="2026-07-06T18:51:19+00:00">
    <testcase classname="AccountRegistrationServiceTest" name="testItRegistersAccount" time="0.005" />
    <testcase classname="AccountRegistrationServiceTest" name="testItRejectsInvalidEmail" time="0.007">
      <failure message="Failed asserting that false is true.">Failed asserting that false is true.</failure>
    </testcase>
  </testsuite>
</testsuites>
XML);

        $factory = $this->makeFactory($projectDir);

        $index = $factory->create();

        self::assertSame('failed', $index['status']);
        self::assertSame(67, $index['progress']);
        self::assertSame(['total' => 2, 'passed' => 1, 'failed' => 1], $index['summary']['types']);
        self::assertSame(['total' => 2, 'passed' => 1, 'failed' => 1], $index['summary']['suites']);
        self::assertSame(['total' => 3, 'passed' => 2, 'failed' => 1], $index['summary']['tests']);
        self::assertCount(2, $index['types']);
        self::assertCount(2, $index['suites']);

        $browserType = $this->findType($index['types'], 'browser-smoke');
        self::assertSame('Browser Smoke', $browserType['displayName']);
        self::assertSame('passed', $browserType['status']);
        self::assertSame(['total' => 1, 'passed' => 1, 'failed' => 0], $browserType['summary']['suites']);
        self::assertSame(['total' => 1, 'passed' => 1, 'failed' => 0], $browserType['summary']['tests']);

        $browserSuite = $browserType['suites'][0];
        self::assertSame('browser-smoke', $browserSuite['type']);
        self::assertSame('Browser Smoke', $browserSuite['typeDisplayName']);
        self::assertSame('transporter-login', $browserSuite['suite']);
        self::assertSame('Transporter Login', $browserSuite['displayName']);
        self::assertSame('passed', $browserSuite['status']);
        self::assertSame($codec->encode('browser-smoke/transporter-login'), $browserSuite['suiteId']);
        self::assertSame('/tests/artifacts/'.$browserSuite['suiteId'].'/report.json', $browserSuite['links']['report']);
        self::assertSame('/tests/artifacts/'.$browserSuite['suiteId'].'/01-login-screen.png', $browserSuite['tests'][0]['screenshots'][0]['url']);
        self::assertSame('image/png', $browserSuite['tests'][0]['screenshots'][0]['mimeType']);
        self::assertSame('image', $browserSuite['tests'][0]['screenshots'][0]['kind']);
        self::assertArrayNotHasKey('path', $browserSuite['tests'][0]['screenshots'][0]);

        $phpunitType = $this->findType($index['types'], 'phpunit');
        self::assertSame('PHPUnit', $phpunitType['displayName']);
        self::assertSame('failed', $phpunitType['status']);
        self::assertSame(['total' => 1, 'passed' => 0, 'failed' => 1], $phpunitType['summary']['suites']);
        self::assertSame(['total' => 2, 'passed' => 1, 'failed' => 1], $phpunitType['summary']['tests']);

        $phpunitSuite = $phpunitType['suites'][0];
        self::assertSame('phpunit', $phpunitSuite['type']);
        self::assertSame('PHPUnit', $phpunitSuite['typeDisplayName']);
        self::assertSame('Unit', $phpunitSuite['suite']);
        self::assertSame('Unit', $phpunitSuite['displayName']);
        self::assertSame('failed', $phpunitSuite['status']);
        self::assertSame($codec->encode('phpunit/accounting'), $phpunitSuite['suiteId']);
        self::assertSame('/tests/artifacts/'.$phpunitSuite['suiteId'].'/report.xml', $phpunitSuite['links']['report']);
        self::assertSame('AccountRegistrationServiceTest::testItRejectsInvalidEmail', $phpunitSuite['tests'][1]['title']);
        self::assertSame('failed', $phpunitSuite['tests'][1]['status']);
        self::assertStringContainsString('Failed asserting that false is true.', $phpunitSuite['tests'][1]['error']);

        self::assertStringContainsString('falha', $index['message']);
        self::assertSame('2026-07-06T18:51:19+00:00', $index['lastRunAt']);
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
        self::assertSame('browser-smoke', $index['suites'][0]['type']);
        self::assertSame('failed', $index['suites'][0]['status']);
        self::assertSame(0, $index['suites'][0]['summary']['total']);
        self::assertNotEmpty($index['suites'][0]['error']);
    }

    private function makeFactory(string $projectDir): SmokeTestsIndexFactory
    {
        $this->resetEnv();
        $_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH'] = $projectDir.'/var/tests';
        putenv('SMOKE_TESTS_PLAYGROUND_TESTS_PATH='.$_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH']);

        $settings = new SmokeTestsSettings($projectDir);

        return new SmokeTestsIndexFactory(
            new SmokeReportReader($settings, new SmokeSuitePathCodec()),
            new SmokeSuitePathCodec(),
        );
    }

    private function makeProjectDir(): string
    {
        $projectDir = sys_get_temp_dir().'/smoke-index-'.bin2hex(random_bytes(6));
        mkdir($projectDir.'/var/tests', 0777, true);

        return $projectDir;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function writeJsonReport(string $projectDir, string $type, string $suite, array $report): void
    {
        $suiteDir = $projectDir.'/var/tests/'.$type.'/'.$suite;
        if (!is_dir($suiteDir)) {
            mkdir($suiteDir, 0777, true);
        }

        file_put_contents(
            $suiteDir.'/report.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    private function writeXmlReport(string $projectDir, string $type, string $suite, string $xml): void
    {
        $suiteDir = $projectDir.'/var/tests/'.$type.'/'.$suite;
        if (!is_dir($suiteDir)) {
            mkdir($suiteDir, 0777, true);
        }

        file_put_contents($suiteDir.'/report.xml', $xml);
    }

    private function writePng(string $projectDir, string $type, string $suite, string $relativePath): void
    {
        $suiteDir = $projectDir.'/var/tests/'.$type.'/'.$suite;
        $filePath = $suiteDir.'/'.$relativePath;
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        file_put_contents($filePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2P5foAAAAASUVORK5CYII='));
    }

    /**
     * @param list<array<string, mixed>> $types
     *
     * @return array<string, mixed>
     */
    private function findType(array $types, string $type): array
    {
        foreach ($types as $entry) {
            if (($entry['type'] ?? null) === $type) {
                return $entry;
            }
        }

        self::fail(sprintf('Type %s not found.', $type));
    }

    private function resetEnv(): void
    {
        unset($_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH']);
        putenv('SMOKE_TESTS_PLAYGROUND_TESTS_PATH');
    }
}
