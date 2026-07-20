<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Service;

use ControleOnline\SmokeTestsPlayground\Service\SmokeArtifactResponseFactory;
use ControleOnline\SmokeTestsPlayground\Service\SmokeRemoteArtifactReaderInterface;
use ControleOnline\SmokeTestsPlayground\Service\SmokeSuitePathCodec;
use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class SmokeArtifactResponseFactoryTest extends TestCase
{
    public function testCreateReturnsTheLocalFileWhenTheArtifactExists(): void
    {
        $projectDir = $this->makeProjectDir();
        $suitePath = 'browser-smoke/transporter-login';
        $suiteId = (new SmokeSuitePathCodec())->encode($suitePath);
        $suiteDir = $projectDir.'/var/tests/'.$suitePath;
        mkdir($suiteDir, 0777, true);
        file_put_contents($suiteDir.'/report.json', '{"ok":true}');

        $factory = new SmokeArtifactResponseFactory(
            new SmokeTestsSettings($projectDir),
            new SmokeSuitePathCodec(),
            $this->makeRemoteReader(null),
        );

        $response = $factory->create($suiteId, 'report.json');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));
        self::assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
    }

    public function testCreateFallsBackToTheRemoteArtifactWhenTheLocalFileIsMissing(): void
    {
        $projectDir = $this->makeProjectDir();
        $suitePath = 'browser-smoke/transporter-login';
        $suiteId = (new SmokeSuitePathCodec())->encode($suitePath);
        $remoteResponse = new Response('remote-artifact', 200, [
            'Content-Type' => 'text/plain',
        ]);

        $factory = new SmokeArtifactResponseFactory(
            new SmokeTestsSettings($projectDir),
            new SmokeSuitePathCodec(),
            $this->makeRemoteReader($remoteResponse),
        );

        $response = $factory->create($suiteId, 'report.json');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain', $response->headers->get('content-type'));
        self::assertSame('remote-artifact', (string) $response->getContent());
    }

    private function makeProjectDir(): string
    {
        $projectDir = sys_get_temp_dir().'/smoke-artifact-'.bin2hex(random_bytes(6));
        mkdir($projectDir.'/var/tests', 0777, true);

        return $projectDir;
    }

    private function makeRemoteReader(?Response $response): SmokeRemoteArtifactReaderInterface
    {
        return new class($response) implements SmokeRemoteArtifactReaderInterface {
            public function __construct(private readonly ?Response $response)
            {
            }

            public function create(string $suiteId, string $artifactPath): ?Response
            {
                return $this->response;
            }
        };
    }
}
