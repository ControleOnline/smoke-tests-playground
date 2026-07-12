<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmokeRemoteArtifactReader implements SmokeRemoteArtifactReaderInterface
{
    public function __construct(
        private readonly SmokeRemoteSourceResolver $sourceResolver,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function create(string $suiteId, string $artifactPath): ?Response
    {
        $url = $this->sourceResolver->remoteArtifactUrl($suiteId, $artifactPath);
        if ($url === null) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                return null;
            }

            $headers = $response->getHeaders(false);
            $content = $response->getContent(false);
        } catch (\Throwable) {
            return null;
        }

        $artifactResponse = new Response($content, $statusCode);
        $artifactResponse->headers->set(
            'Content-Type',
            $this->readHeader($headers, 'content-type') ?? $this->mimeTypeFromPath($artifactPath),
        );
        $artifactResponse->headers->set('X-Content-Type-Options', 'nosniff');
        $artifactResponse->headers->set('Cache-Control', 'private, no-store, max-age=0');

        try {
            $artifactResponse->headers->set(
                'Content-Disposition',
                $artifactResponse->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_INLINE,
                    basename($artifactPath),
                ),
            );
        } catch (\Throwable) {
            $artifactResponse->headers->set('Content-Disposition', ResponseHeaderBag::DISPOSITION_INLINE.'; filename="'.basename($artifactPath).'"');
        }

        return $artifactResponse;
    }

    /**
     * @param array<string, array<int, string>|string> $headers
     */
    private function readHeader(array $headers, string $name): ?string
    {
        $value = $headers[$name] ?? null;
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function mimeTypeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'avif' => 'image/avif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'json' => 'application/json',
            'txt', 'md' => 'text/plain; charset=UTF-8',
            default => 'application/octet-stream',
        };
    }
}
