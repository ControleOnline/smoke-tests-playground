<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Controller;

use ControleOnline\SmokeTestsPlayground\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class SmokeTestsControllerTest extends KernelTestCase
{
    private string $projectDir;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureKernelShutdown();
        $this->projectDir = $this->makeProjectDir();
        $_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH'] = $this->projectDir.'/var/tests';
        putenv('SMOKE_TESTS_PLAYGROUND_TESTS_PATH='.$_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH']);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        unset($_ENV['SMOKE_TESTS_PLAYGROUND_TESTS_PATH']);
        putenv('SMOKE_TESTS_PLAYGROUND_TESTS_PATH');
    }

    public function testIndexEndpointsReturnTheSameJsonPayload(): void
    {
        $this->writeReport('browser-smoke', 'transporter-login', [
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
        $this->writePng('browser-smoke', 'transporter-login', '01-login-screen.png');

        self::bootKernel();

        $httpKernel = self::getContainer()->get('http_kernel');
        $response = $httpKernel->handle(Request::create('/tests'));
        $indexJsonResponse = $httpKernel->handle(Request::create('/tests/index.json'));
        $apiResponse = $httpKernel->handle(Request::create('/tests/api'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));
        self::assertSame($response->getContent(), $indexJsonResponse->getContent());
        self::assertSame($response->getContent(), $apiResponse->getContent());

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('passed', $payload['status']);
        self::assertSame(100, $payload['progress']);
        self::assertSame(['total' => 1, 'passed' => 1, 'failed' => 0], $payload['summary']['types']);
        self::assertSame(['total' => 1, 'passed' => 1, 'failed' => 0], $payload['summary']['suites']);
        self::assertSame(['total' => 1, 'passed' => 1, 'failed' => 0], $payload['summary']['tests']);
        self::assertCount(1, $payload['types']);
        self::assertCount(1, $payload['suites']);

        $browserType = $this->findType($payload['types'], 'browser-smoke');
        $browserSuite = $browserType['suites'][0];

        self::assertSame('Browser Smoke', $browserType['displayName']);
        self::assertSame('/tests/artifacts/'.$browserSuite['suiteId'].'/report.json', $browserSuite['links']['report']);
        self::assertSame('/tests/artifacts/'.$browserSuite['suiteId'].'/01-login-screen.png', $browserSuite['tests'][0]['screenshots'][0]['url']);
        self::assertArrayNotHasKey('path', $browserSuite['tests'][0]['screenshots'][0]);
    }

    public function testArtifactRouteReturnsTheFileContents(): void
    {
        $this->writeReport('browser-smoke', 'transporter-login', [
            'generatedAt' => '2026-07-06T17:42:40.016Z',
            'suite' => 'transporter-login',
            'tests' => [],
        ]);
        $this->writePng('browser-smoke', 'transporter-login', '01-login-screen.png');

        self::bootKernel();

        $indexPayload = json_decode(
            (string) self::getContainer()->get('http_kernel')->handle(Request::create('/tests'))->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $browserType = $this->findType($indexPayload['types'], 'browser-smoke');
        $suiteId = $browserType['suites'][0]['suiteId'];

        $response = self::getContainer()->get('http_kernel')->handle(
            Request::create('/tests/artifacts/'.$suiteId.'/01-login-screen.png'),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->headers->get('content-type'));
        self::assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
    }

    private function makeProjectDir(): string
    {
        $projectDir = sys_get_temp_dir().'/smoke-controller-'.bin2hex(random_bytes(6));
        mkdir($projectDir.'/var/tests', 0777, true);

        return $projectDir;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function writeReport(string $type, string $suite, array $report): void
    {
        $suiteDir = $this->projectDir.'/var/tests/'.$type.'/'.$suite;
        if (!is_dir($suiteDir)) {
            mkdir($suiteDir, 0777, true);
        }

        file_put_contents(
            $suiteDir.'/report.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    private function writePng(string $type, string $suite, string $relativePath): void
    {
        $suiteDir = $this->projectDir.'/var/tests/'.$type.'/'.$suite;
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
}
