<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class SmokeArtifactResponseFactory
{
    public function __construct(
        private readonly SmokeTestsSettings $settings,
        private readonly SmokeSuitePathCodec $suitePathCodec,
        private readonly SmokeRemoteArtifactReaderInterface $remoteArtifactReader,
    ) {
    }

    public function create(string $suiteId, string $artifactPath): Response
    {
        $resolvedPath = $this->resolveArtifactPath($suiteId, $artifactPath);
        if ($resolvedPath === null) {
            $remoteResponse = $this->remoteArtifactReader->create($suiteId, $artifactPath);
            if ($remoteResponse instanceof Response) {
                return $remoteResponse;
            }

            return new JsonResponse([
                'status' => 'failed',
                'message' => 'Artifact not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($resolvedPath);
        $response->headers->set('Content-Type', $this->mimeTypeFromPath($resolvedPath));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($resolvedPath));

        return $response;
    }

    private function resolveArtifactPath(string $suiteId, string $artifactPath): ?string
    {
        $suitePath = $this->suitePathCodec->decode($suiteId);
        if ($suitePath === null) {
            return null;
        }

        $baseDirectory = rtrim($this->settings->testsPath(), '/\\');
        $suiteDirectory = $baseDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $suitePath);
        $suiteRealPath = realpath($suiteDirectory);

        if ($suiteRealPath === false || !is_dir($suiteRealPath)) {
            return null;
        }

        $relativePath = $this->suitePathCodec->normalizeRelativePath($artifactPath);
        if ($relativePath === null) {
            return null;
        }

        $candidatePath = $suiteRealPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $resolvedPath = realpath($candidatePath);

        if ($resolvedPath === false || !is_file($resolvedPath) || !str_starts_with($resolvedPath, $suiteRealPath.DIRECTORY_SEPARATOR) && $resolvedPath !== $suiteRealPath) {
            return null;
        }

        return $resolvedPath;
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
            'txt' => 'text/plain; charset=UTF-8',
            default => 'application/octet-stream',
        };
    }
}
