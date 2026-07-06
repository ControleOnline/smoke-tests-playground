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
    ) {
    }

    public function create(string $suite, string $artifactPath): Response
    {
        $resolvedPath = $this->resolveArtifactPath($suite, $artifactPath);
        if ($resolvedPath === null) {
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

    private function resolveArtifactPath(string $suite, string $artifactPath): ?string
    {
        $baseDirectory = rtrim($this->settings->testsPath(), '/\\');
        $suiteDirectory = $baseDirectory.DIRECTORY_SEPARATOR.$suite;
        $suiteRealPath = realpath($suiteDirectory);

        if ($suiteRealPath === false || !is_dir($suiteRealPath)) {
            return null;
        }

        $relativePath = $this->normalizeRelativePath($artifactPath);
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

    private function normalizeRelativePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);
        $cleanSegments = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }

            $cleanSegments[] = $segment;
        }

        return implode('/', $cleanSegments);
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
