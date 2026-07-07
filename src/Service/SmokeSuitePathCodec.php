<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeSuitePathCodec
{
    private const SPECIAL_LABELS = [
        'browser-smoke' => 'Browser Smoke',
        'integration' => 'Integration',
        'junit' => 'JUnit',
        'phpunit' => 'PHPUnit',
        'unit' => 'Unit',
    ];

    public function encode(string $suitePath): string
    {
        $normalized = $this->normalizeRelativePath($suitePath);
        if ($normalized === null) {
            return '';
        }

        return rtrim(strtr(base64_encode($normalized), '+/', '-_'), '=');
    }

    public function decode(string $suiteId): ?string
    {
        $suiteId = trim($suiteId);
        if ($suiteId === '') {
            return null;
        }

        $base64 = strtr($suiteId, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            return null;
        }

        return $this->normalizeRelativePath($decoded);
    }

    public function relativePath(string $baseDirectory, string $path): ?string
    {
        $baseDirectory = $this->normalizeFilesystemPath((string) (realpath($baseDirectory) ?: $baseDirectory));
        $path = $this->normalizeFilesystemPath((string) (realpath($path) ?: $path));

        if ($baseDirectory === '' || $path === '') {
            return null;
        }

        if ($path === $baseDirectory) {
            return '';
        }

        $prefix = $baseDirectory.'/';
        if (!str_starts_with($path, $prefix)) {
            return null;
        }

        return substr($path, strlen($prefix));
    }

    public function normalizeRelativePath(string $path): ?string
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

    public function normalizeKey(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $normalized = preg_replace('/[^a-zA-Z0-9]+/', '-', $value);
        if (!is_string($normalized)) {
            $normalized = $value;
        }

        $normalized = trim($normalized, '-');

        return strtolower($normalized);
    }

    public function humanizeLabel(string $value): string
    {
        $key = $this->normalizeKey($value);

        if ($key !== '' && isset(self::SPECIAL_LABELS[$key])) {
            return self::SPECIAL_LABELS[$key];
        }

        $label = trim(str_replace(['-', '_'], ' ', $value));
        if ($label === '') {
            return 'Sem nome';
        }

        $label = preg_replace('/\s+/', ' ', $label);
        if (!is_string($label)) {
            $label = $value;
        }

        return ucwords(strtolower($label));
    }

    private function normalizeFilesystemPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        return rtrim($path, '/');
    }
}
