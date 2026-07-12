<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Service;

use ControleOnline\Service\DomainService;
use ControleOnline\SmokeTestsPlayground\Service\SmokeRemoteIndexReader;
use ControleOnline\SmokeTestsPlayground\Service\SmokeRemoteSourceResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SmokeRemoteIndexReaderTest extends TestCase
{
    public function testReadSuitesFetchesTheRemoteFrontIndexWhenTheDomainDiffers(): void
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options = []) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new MockResponse(json_encode([
                'suites' => [
                    [
                        'type' => 'automated',
                        'typeDisplayName' => 'Automated',
                        'suite' => 'home-page',
                        'suitePath' => 'automated/home-page',
                        'suiteId' => 'YXV0b21hdGVkL2hvbWUtcGFnZQ',
                        'displayName' => 'Home Page',
                        'generatedAt' => '2026-07-06T18:51:19.924Z',
                        'updatedAt' => '2026-07-06T18:51:19.924Z',
                        'status' => 'passed',
                        'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0],
                        'tests' => [],
                        'error' => null,
                        'links' => ['report' => '/tests/artifacts/YXV0b21hdGVkL2hvbWUtcGFnZQ/report.json'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => [
                    'Content-Type: application/json',
                ],
            ]);
        });

        $reader = new SmokeRemoteIndexReader(
            new SmokeRemoteSourceResolver($this->makeDomainService(
                's.controleonline.com',
                'admin.controleonline.com',
            )),
            $httpClient,
        );

        $suites = $reader->readSuites();

        self::assertSame('https://admin.controleonline.com/tests/index.json', $capturedUrl);
        self::assertCount(1, $suites);
        self::assertSame('automated', $suites[0]['type']);
    }

    public function testReadSuitesSkipsTheRemoteFetchWhenTheDomainMatchesTheApiHost(): void
    {
        $requests = 0;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options = []) use (&$requests) {
            $requests++;

            return new MockResponse('{"suites":[]}', [
                'http_code' => 200,
                'response_headers' => [
                    'Content-Type: application/json',
                ],
            ]);
        });

        $reader = new SmokeRemoteIndexReader(
            new SmokeRemoteSourceResolver($this->makeDomainService(
                's.controleonline.com',
                's.controleonline.com',
            )),
            $httpClient,
        );

        self::assertSame([], $reader->readSuites());
        self::assertSame(0, $requests);
    }

    private function makeDomainService(string $mainDomain, string $appDomain): DomainService
    {
        $requestStack = new RequestStack();
        $request = Request::create('https://'.$mainDomain.'/tests');
        $request->headers->set('app-domain', $appDomain);
        $requestStack->push($request);

        return new DomainService(
            $this->createStub(EntityManagerInterface::class),
            $requestStack,
        );
    }
}
