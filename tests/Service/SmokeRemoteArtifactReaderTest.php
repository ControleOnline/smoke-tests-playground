<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Service;

use ControleOnline\Service\DomainService;
use ControleOnline\SmokeTestsPlayground\Service\SmokeRemoteArtifactReader;
use ControleOnline\SmokeTestsPlayground\Service\SmokeRemoteSourceResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SmokeRemoteArtifactReaderTest extends TestCase
{
    public function testCreateFetchesTheRemoteArtifactWithTlsVerificationDisabledForTheInternalFront(): void
    {
        $capturedUrl = null;
        $capturedOptions = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options = []) use (&$capturedUrl, &$capturedOptions) {
            $capturedUrl = $url;
            $capturedOptions = $options;

            return new MockResponse('remote-artifact', [
                'http_code' => 200,
                'response_headers' => [
                    'Content-Type: text/plain',
                ],
            ]);
        });

        $reader = new SmokeRemoteArtifactReader(
            new SmokeRemoteSourceResolver($this->makeDomainService(
                's.controleonline.com',
                'admin.controleonline.com',
            )),
            $httpClient,
        );

        $response = $reader->create('YXV0b21hdGVkL2hvbWUtcGFnZQ', 'report.json');

        self::assertSame('https://admin.controleonline.com/tests/artifacts/YXV0b21hdGVkL2hvbWUtcGFnZQ/report.json', $capturedUrl);
        self::assertFalse($capturedOptions['verify_peer'] ?? true);
        self::assertFalse($capturedOptions['verify_host'] ?? true);
        self::assertEquals(10, $capturedOptions['timeout'] ?? null);
        self::assertSame(200, $response?->getStatusCode());
        self::assertSame('text/plain', $response?->headers->get('content-type'));
        self::assertSame('remote-artifact', (string) $response?->getContent());
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
