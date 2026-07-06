<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Controller;

use ControleOnline\SmokeTestsPlayground\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class SmokeTestsControllerTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testIndexRendersTwigPage(): void
    {
        self::bootKernel();

        $response = self::getContainer()->get('http_kernel')->handle(Request::create('/tests'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Smoke tests playground', (string) $response->getContent());
        self::assertStringContainsString('data-tests-endpoint="&#x2F;tests&#x2F;api"', (string) $response->getContent());
    }

    public function testApiReturnsOnlyPublicFields(): void
    {
        self::bootKernel();

        $response = self::getContainer()->get('http_kernel')->handle(Request::create('/tests/api'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('content-type'));

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['status', 'progress', 'message', 'lastRunAt', 'summary', 'tests'], array_keys($payload));
        self::assertArrayNotHasKey('testsPath', $payload);
        self::assertArrayNotHasKey('reportPath', $payload);
        self::assertArrayNotHasKey('runCommand', $payload);
        self::assertArrayNotHasKey('runWorkingDirectory', $payload);
        self::assertArrayNotHasKey('runTimeout', $payload);
        self::assertArrayNotHasKey('report', $payload);
    }
}
